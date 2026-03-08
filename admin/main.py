from datetime import datetime, timedelta
from typing import Any, Optional
import os
import time
import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import mysql.connector
from cryptography.fernet import Fernet
from apscheduler.schedulers.background import BackgroundScheduler
from apscheduler.triggers.interval import IntervalTrigger

# Try to import MT5 (optional - only if local terminal available)
try:
    import MetaTrader5 as mt5
    MT5_LOCAL_AVAILABLE = True
except ImportError:
    MT5_LOCAL_AVAILABLE = False
    mt5 = None
    logger.warning("MetaTrader5 library not available - using cloud sync only")

# Import cloud sync module
try:
    from mt5_cloud import MT5CloudSync
    CLOUD_SYNC_AVAILABLE = True
except ImportError:
    try:
        from admin.mt5_cloud import MT5CloudSync
        CLOUD_SYNC_AVAILABLE = True
    except ImportError:
        CLOUD_SYNC_AVAILABLE = False
        MT5CloudSync = None
        logger.warning("Cloud sync module not available")

# --- Logging setup ---
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# --- MySQL configuration (same DB as your PHP app) ---
# XAMPP default को लागि यी value ठीक हुन्छन्, आफ्नो DB अनुसार change गर्न सक्नुहुन्छ
DB_HOST = "127.0.0.1"
DB_USER = "root"
DB_PASSWORD = ""  # XAMPP default: खाली password
DB_NAME = "trading_db"

# Optional: MT5 terminal path (यदि auto initialize fail हुन्छ भने path राख्नुहोस्)
MT5_TERMINAL_PATH: str | None = os.getenv("MT5_TERMINAL_PATH") or r"C:\Program Files\MetaTrader 5\terminal64.exe"

# Fernet key for password encryption
# IMPORTANT: Production मा यो key .env file मा राख्नुहोस् र कहिल्यै expose नगर्नुहोस्
# Generate key: python -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
FERNET_KEY = os.getenv("FERNET_KEY", "umKPzbDdTqeXr2RVYwL4cg3RcLW8-gKX_nJLDmkGlG0=")
try:
    fernet = Fernet(FERNET_KEY.encode())
except Exception as e:
    logger.error(f"Invalid FERNET_KEY: {e}")
    # Generate a new key if invalid (for demo only - production मा proper key राख्नुहोस्)
    key = Fernet.generate_key()
    fernet = Fernet(key)
    logger.warning("Using auto-generated Fernet key - change FERNET_KEY in production!")

# Scheduler for auto-sync
scheduler = BackgroundScheduler(timezone="UTC")


def encrypt_password(password: str) -> str:
    """Encrypt investor password using Fernet."""
    return fernet.encrypt(password.encode()).decode()


def decrypt_password(encrypted: str) -> str:
    """Decrypt investor password."""
    return fernet.decrypt(encrypted.encode()).decode()


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup: Start scheduler
    scheduler.start()
    logger.info("MT5 Auto-sync scheduler started (every 5 minutes)")
    yield
    # Shutdown: Stop scheduler
    scheduler.shutdown()
    logger.info("MT5 Auto-sync scheduler stopped")


app = FastAPI(title="MT5 Sync API", lifespan=lifespan)

# CORS middleware (allow PHP frontend)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Production मा specific domain राख्नुहोस्
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def get_db_connection():
    return mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
    )


@app.get("/ping")
def ping():
    return {"status": "ok"}


class ConnectAccountRequest(BaseModel):
    account_number: str
    broker_server: str
    investor_password: str
    user_id: int
    account_id: int
    sync_method: str = "local"  # "local" or "cloud" - local requires MT5 terminal, cloud uses API
    cloud_api_key: Optional[str] = None  # Required if sync_method="cloud" and using MT Connect API
    cloud_api_url: Optional[str] = None  # Optional custom broker API URL


def map_order_type(deal_type: int) -> str:
    """Basic mapping from MT5 deal type to human readable string."""
    if not MT5_LOCAL_AVAILABLE or mt5 is None:
        return str(deal_type)
    try:
        if deal_type == mt5.DEAL_TYPE_BUY:
            return "BUY"
        if deal_type == mt5.DEAL_TYPE_SELL:
            return "SELL"
        if deal_type == mt5.DEAL_TYPE_BALANCE:
            return "BALANCE"
    except AttributeError:
        pass
    return str(deal_type)


def sync_mt5_trades(req: ConnectAccountRequest) -> int:
    """
    Connect to MT5 and fetch trades - supports both LOCAL (terminal) and CLOUD (API) methods.
    """
    # Determine sync method
    use_cloud = req.sync_method == "cloud" or (req.sync_method == "local" and not MT5_LOCAL_AVAILABLE)
    
    if use_cloud:
        # Cloud sync (no local terminal required)
        if not CLOUD_SYNC_AVAILABLE:
            raise HTTPException(status_code=500, detail="Cloud sync not available - install requests library")
        
        # Determine API type
        api_type = "mtconnect" if req.cloud_api_key else "broker_rest"
        cloud_sync = MT5CloudSync(
            api_type=api_type,
            api_key=req.cloud_api_key,
            api_url=req.cloud_api_url,
        )
        
        # Fetch trades via cloud API
        to_time = datetime.now()
        from_time = to_time - timedelta(days=365 * 2)
        
        try:
            trades = cloud_sync.sync_trades(
                account_number=req.account_number,
                broker_server=req.broker_server,
                investor_password=req.investor_password,
                from_date=from_time,
                to_date=to_time,
            )
        except Exception as e:
            raise HTTPException(status_code=400, detail=f"Cloud sync failed: {str(e)}")
        
        # Convert to format compatible with MySQL insert
        rows_to_insert = []
        conn = get_db_connection()
        try:
            cur = conn.cursor()
            cur.execute(
                "SELECT MAX(ticket) FROM mt5_trades WHERE user_id=%s AND account_id=%s",
                (req.user_id, req.account_id),
            )
            row = cur.fetchone()
            last_ticket = row[0] if row and row[0] is not None else 0

            for trade in trades:
                ticket = trade["ticket"]
                if last_ticket and ticket <= last_ticket:
                    continue

                rows_to_insert.append(
                    (
                        req.user_id,
                        req.account_id,
                        int(ticket),
                        trade["symbol"],
                        float(trade["volume"]),
                        trade["order_type"],
                        trade["open_time"],
                        trade["close_time"],
                        float(trade["open_price"]),
                        float(trade["close_price"]),
                        float(trade["profit"]),
                        float(trade["commission"]),
                        float(trade["swap"]),
                        trade["magic"],
                        trade["comment"],
                    )
                )

            if rows_to_insert:
                cur.executemany(
                    """
                    INSERT INTO mt5_trades
                        (user_id, account_id, ticket, symbol, volume, order_type,
                         open_time, close_time, open_price, close_price,
                         profit, commission, swap, magic, comment)
                    VALUES
                        (%s, %s, %s, %s, %s, %s,
                         %s, %s, %s, %s,
                         %s, %s, %s, %s, %s)
                    """,
                    rows_to_insert,
                )
                conn.commit()

            return len(rows_to_insert)
        finally:
            conn.close()
    
    else:
        # Local sync (requires MT5 terminal)
        if not MT5_LOCAL_AVAILABLE:
            raise HTTPException(status_code=500, detail="Local MT5 sync requires MetaTrader5 library and terminal installation")
        
        init_kwargs: dict[str, Any] = {}
        if MT5_TERMINAL_PATH and os.path.exists(MT5_TERMINAL_PATH):
            init_kwargs["path"] = MT5_TERMINAL_PATH

        if not mt5.initialize(**init_kwargs):
            code, msg = mt5.last_error()
            raise HTTPException(status_code=500, detail=f"MT5 initialize failed: {code} {msg}")

        try:
            # Login with investor password (retry a few times for IPC flakiness)
            ok = False
            for attempt in range(1, 4):
                ok = mt5.login(
                    login=int(req.account_number),
                    password=req.investor_password,
                    server=req.broker_server,
                )
                if ok:
                    break

                last_code, last_msg = mt5.last_error()
                if last_code == -10001:
                    time.sleep(1.5 * attempt)
                    continue
                break

            if not ok:
                code, msg = mt5.last_error()
                hint = ""
                if code == -10001:
                    hint = " (Hint: MT5 terminal install/run भएको छ? same Windows user/session मा चलिरहेको छ? MT5 एकपटक manually open गरेर login गरेर राख्नुहोस्.)"
                raise HTTPException(status_code=400, detail=f"MT5 login failed: {code} {msg}{hint}")

            # Hard-verify account
            info = mt5.account_info()
            if info is None:
                code, msg = mt5.last_error()
                raise HTTPException(status_code=500, detail=f"MT5 account_info failed: {code} {msg}")
            if int(getattr(info, "login", -1)) != int(req.account_number):
                raise HTTPException(status_code=400, detail="MT5 login mismatch (connected to different account)")

            # Fetch history (last 2 years)
            to_time = datetime.now()
            from_time = to_time - timedelta(days=365 * 2)

            deals = mt5.history_deals_get(from_time, to_time)
            if deals is None:
                code, msg = mt5.last_error()
                raise HTTPException(status_code=500, detail=f"history_deals_get failed: {code} {msg}")
        finally:
            mt5.shutdown()

        # Save to MySQL
        conn = get_db_connection()
        try:
            cur = conn.cursor()

            cur.execute(
                "SELECT MAX(ticket) FROM mt5_trades WHERE user_id=%s AND account_id=%s",
                (req.user_id, req.account_id),
            )
            row = cur.fetchone()
            last_ticket = row[0] if row and row[0] is not None else 0

            rows_to_insert = []
            for d in deals:
                ticket = d.ticket
                if last_ticket and ticket <= last_ticket:
                    continue

                order_type = map_order_type(d.type)

                deal_time = d.time
                if isinstance(deal_time, (int, float)):
                    deal_time = datetime.fromtimestamp(deal_time)

                open_time = deal_time
                close_time = deal_time

                rows_to_insert.append(
                    (
                        req.user_id,
                        req.account_id,
                        int(ticket),
                        d.symbol,
                        float(d.volume),
                        order_type,
                        open_time,
                        close_time,
                        float(d.price),
                        float(d.price),
                        float(d.profit),
                        float(d.commission),
                        float(d.swap),
                        int(d.magic) if d.magic is not None else None,
                        d.comment,
                    )
                )

            if rows_to_insert:
                cur.executemany(
                    """
                    INSERT INTO mt5_trades
                        (user_id, account_id, ticket, symbol, volume, order_type,
                         open_time, close_time, open_price, close_price,
                         profit, commission, swap, magic, comment)
                    VALUES
                        (%s, %s, %s, %s, %s, %s,
                         %s, %s, %s, %s,
                         %s, %s, %s, %s, %s)
                    """,
                    rows_to_insert,
                )
                conn.commit()

            return len(rows_to_insert)
        finally:
            conn.close()


@app.post("/api/v1/accounts/connect")
def connect_account(payload: ConnectAccountRequest):
    """
    Connect MT5 account using investor password, store connection info, and sync trades.
    Similar to TradeFXBook - stores encrypted password for auto-sync.
    """
    conn = get_db_connection()
    try:
        cur = conn.cursor()

        # Encrypt password
        encrypted_pwd = encrypt_password(payload.investor_password)

        # Store or update MT5 account connection
        cur.execute(
            """
            INSERT INTO mt5_accounts
                (user_id, account_id, mt5_account_number, mt5_broker_server, mt5_password_encrypted, is_active)
            VALUES (%s, %s, %s, %s, %s, 1)
            ON DUPLICATE KEY UPDATE
                mt5_password_encrypted = VALUES(mt5_password_encrypted),
                is_active = 1,
                sync_error = NULL,
                updated_at = CURRENT_TIMESTAMP
            """,
            (
                payload.user_id,
                payload.account_id,
                payload.account_number,
                payload.broker_server,
                encrypted_pwd,
            ),
        )
        conn.commit()

        # Sync trades
        inserted = sync_mt5_trades(payload)

        # Update last_sync_at
        cur.execute(
            "UPDATE mt5_accounts SET last_sync_at = NOW(), last_sync_ticket = (SELECT MAX(ticket) FROM mt5_trades WHERE user_id=%s AND account_id=%s) WHERE user_id=%s AND account_id=%s AND mt5_account_number=%s",
            (payload.user_id, payload.account_id, payload.user_id, payload.account_id, payload.account_number),
        )
        conn.commit()

        return {
            "status": "ok",
            "inserted_trades": inserted,
            "message": f"MT5 account connected and {inserted} new trades synced",
        }
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error in connect_account: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"Database error: {str(e)}")
    finally:
        conn.close()


@app.post("/api/v1/accounts/{mt5_account_id}/sync")
def manual_sync(mt5_account_id: int):
    """
    Manual sync endpoint - trigger sync for a specific MT5 account.
    """
    conn = get_db_connection()
    try:
        cur = conn.cursor()
        cur.execute(
            """
            SELECT user_id, account_id, mt5_account_number, mt5_broker_server, mt5_password_encrypted
            FROM mt5_accounts WHERE id = %s AND is_active = 1
            """,
            (mt5_account_id,),
        )
        row = cur.fetchone()
        if not row:
            raise HTTPException(status_code=404, detail="MT5 account not found or inactive")

        user_id, account_id, account_number, broker_server, encrypted_pwd = row
        decrypted_pwd = decrypt_password(encrypted_pwd)

        req = ConnectAccountRequest(
            account_number=account_number,
            broker_server=broker_server,
            investor_password=decrypted_pwd,
            user_id=user_id,
            account_id=account_id,
        )

        inserted = sync_mt5_trades(req)

        # Update sync status
        cur.execute(
            "UPDATE mt5_accounts SET last_sync_at = NOW(), last_sync_ticket = (SELECT MAX(ticket) FROM mt5_trades WHERE user_id=%s AND account_id=%s), sync_error = NULL WHERE id = %s",
            (user_id, account_id, mt5_account_id),
        )
        conn.commit()

        return {"status": "ok", "inserted_trades": inserted}
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error in manual_sync: {e}", exc_info=True)
        # Store error in database
        try:
            cur.execute("UPDATE mt5_accounts SET sync_error = %s WHERE id = %s", (str(e), mt5_account_id))
            conn.commit()
        except:
            pass
        raise HTTPException(status_code=500, detail=f"Sync failed: {str(e)}")
    finally:
        conn.close()


def auto_sync_all_accounts():
    """
    Background job - sync all active MT5 accounts (called by scheduler every 5 minutes).
    """
    conn = get_db_connection()
    try:
        cur = conn.cursor()
        cur.execute(
            """
            SELECT id, user_id, account_id, mt5_account_number, mt5_broker_server, mt5_password_encrypted
            FROM mt5_accounts WHERE is_active = 1
            """
        )
        accounts = cur.fetchall()

        logger.info(f"Auto-sync: Processing {len(accounts)} active MT5 accounts")

        for row in accounts:
            mt5_id, user_id, account_id, account_number, broker_server, encrypted_pwd = row
            try:
                decrypted_pwd = decrypt_password(encrypted_pwd)
                req = ConnectAccountRequest(
                    account_number=account_number,
                    broker_server=broker_server,
                    investor_password=decrypted_pwd,
                    user_id=user_id,
                    account_id=account_id,
                )

                inserted = sync_mt5_trades(req)
                logger.info(f"Auto-sync: Account {mt5_id} - {inserted} new trades")

                # Update sync status
                cur.execute(
                    "UPDATE mt5_accounts SET last_sync_at = NOW(), last_sync_ticket = (SELECT MAX(ticket) FROM mt5_trades WHERE user_id=%s AND account_id=%s), sync_error = NULL WHERE id = %s",
                    (user_id, account_id, mt5_id),
                )
                conn.commit()
            except Exception as e:
                logger.error(f"Auto-sync failed for account {mt5_id}: {e}")
                # Store error
                try:
                    cur.execute("UPDATE mt5_accounts SET sync_error = %s WHERE id = %s", (str(e)[:500], mt5_id))
                    conn.commit()
                except:
                    pass

    except Exception as e:
        logger.error(f"Auto-sync job error: {e}", exc_info=True)
    finally:
        conn.close()


# Schedule auto-sync every 5 minutes (like TradeFXBook)
scheduler.add_job(
    auto_sync_all_accounts,
    trigger=IntervalTrigger(minutes=5),
    id="mt5_auto_sync",
    replace_existing=True,
)