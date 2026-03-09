"""
MT5 Cloud Sync Module - Works WITHOUT local MT5 terminal installation
Uses MT Connect API or broker REST APIs to fetch trade history directly from MT5 servers.
"""
from datetime import datetime, timedelta
from typing import Any, Optional
import requests
import logging

logger = logging.getLogger(__name__)


class MT5CloudSync:
    """
    Cloud-based MT5 sync using external APIs (no local terminal required).
    Supports:
    1. MT Connect API (mtconnectapi.com)
    2. Broker-specific REST APIs
    """

    def __init__(self, api_type: str = "mtconnect", api_key: Optional[str] = None, api_url: Optional[str] = None):
        """
        Initialize cloud sync.
        
        Args:
            api_type: "mtconnect" or "broker_rest"
            api_key: API key for MT Connect API (if using mtconnect)
            api_url: Custom broker REST API URL (if using broker_rest)
        """
        self.api_type = api_type
        self.api_key = api_key
        self.api_url = api_url or "https://mtconnectapi.com/api/v1"

    def sync_trades(
        self,
        account_number: str,
        broker_server: str,
        investor_password: str,
        from_date: Optional[datetime] = None,
        to_date: Optional[datetime] = None,
    ) -> list[dict]:
        """
        Fetch trade history from MT5 server via cloud API.
        
        Returns list of trade dicts with keys:
        - ticket, symbol, volume, order_type, open_time, close_time,
        - open_price, close_price, profit, commission, swap, magic, comment
        """
        if self.api_type == "mtconnect":
            return self._sync_via_mtconnect(account_number, broker_server, investor_password, from_date, to_date)
        elif self.api_type == "broker_rest":
            return self._sync_via_broker_rest(account_number, broker_server, investor_password, from_date, to_date)
        else:
            raise ValueError(f"Unknown API type: {self.api_type}")

    def _sync_via_mtconnect(
        self,
        account_number: str,
        broker_server: str,
        investor_password: str,
        from_date: Optional[datetime],
        to_date: Optional[datetime],
    ) -> list[dict]:
        """
        Sync using MT Connect API (https://mtconnectapi.com)
        Requires API key from mtconnectapi.com
        """
        if not self.api_key:
            raise ValueError("MT Connect API key required. Get one from https://mtconnectapi.com")

        # MT Connect API endpoint
        url = f"{self.api_url}/history"
        
        params = {
            "account": account_number,
            "server": broker_server,
            "password": investor_password,
            "api_key": self.api_key,
        }

        if from_date:
            params["from"] = from_date.strftime("%Y-%m-%d")
        if to_date:
            params["to"] = to_date.strftime("%Y-%m-%d")

        try:
            response = requests.get(url, params=params, timeout=30)
            response.raise_for_status()
            data = response.json()

            if data.get("status") != "success":
                raise ValueError(f"MT Connect API error: {data.get('message', 'Unknown error')}")

            trades = []
            for deal in data.get("deals", []):
                trades.append({
                    "ticket": int(deal.get("ticket", 0)),
                    "symbol": deal.get("symbol", ""),
                    "volume": float(deal.get("volume", 0)),
                    "order_type": deal.get("type", "UNKNOWN"),
                    "open_time": datetime.fromisoformat(deal.get("open_time", "").replace("Z", "+00:00")),
                    "close_time": datetime.fromisoformat(deal.get("close_time", "").replace("Z", "+00:00")),
                    "open_price": float(deal.get("open_price", 0)),
                    "close_price": float(deal.get("close_price", 0)),
                    "profit": float(deal.get("profit", 0)),
                    "commission": float(deal.get("commission", 0)),
                    "swap": float(deal.get("swap", 0)),
                    "magic": int(deal.get("magic", 0)) if deal.get("magic") else None,
                    "comment": deal.get("comment", ""),
                })

            return trades

        except requests.RequestException as e:
            logger.error(f"MT Connect API request failed: {e}")
            raise ValueError(f"MT Connect API error: {str(e)}")

    def _sync_via_broker_rest(
        self,
        account_number: str,
        broker_server: str,
        investor_password: str,
        from_date: Optional[datetime],
        to_date: Optional[datetime],
    ) -> list[dict]:
        """
        Sync using broker's own REST API (if available).
        Customize this method based on your broker's API documentation.
        """
        if not self.api_url:
            raise ValueError("Broker REST API URL required")

        # Example: Custom broker API call
        # Adjust based on your broker's API documentation
        url = f"{self.api_url}/trades/history"
        
        payload = {
            "account": account_number,
            "server": broker_server,
            "password": investor_password,
        }

        if from_date:
            payload["from"] = from_date.isoformat()
        if to_date:
            payload["to"] = to_date.isoformat()

        try:
            response = requests.post(url, json=payload, timeout=30)
            response.raise_for_status()
            data = response.json()

            # Convert broker API response to standard format
            trades = []
            for deal in data.get("trades", []):
                trades.append({
                    "ticket": int(deal.get("ticket", 0)),
                    "symbol": deal.get("symbol", ""),
                    "volume": float(deal.get("volume", 0)),
                    "order_type": deal.get("type", "UNKNOWN"),
                    "open_time": datetime.fromisoformat(deal.get("open_time", "")),
                    "close_time": datetime.fromisoformat(deal.get("close_time", "")),
                    "open_price": float(deal.get("open_price", 0)),
                    "close_price": float(deal.get("close_price", 0)),
                    "profit": float(deal.get("profit", 0)),
                    "commission": float(deal.get("commission", 0)),
                    "swap": float(deal.get("swap", 0)),
                    "magic": int(deal.get("magic", 0)) if deal.get("magic") else None,
                    "comment": deal.get("comment", ""),
                })

            return trades

        except requests.RequestException as e:
            logger.error(f"Broker REST API request failed: {e}")
            raise ValueError(f"Broker API error: {str(e)}")


# Example usage:
if __name__ == "__main__":
    # Option 1: MT Connect API (requires API key from mtconnectapi.com)
    sync = MT5CloudSync(api_type="mtconnect", api_key="your-api-key-here")
    
    # Option 2: Broker REST API (custom URL)
    # sync = MT5CloudSync(api_type="broker_rest", api_url="https://your-broker-api.com/v1")
    
    trades = sync.sync_trades(
        account_number="12345678",
        broker_server="ICMarketsSC-Real",
        investor_password="your-investor-password",
        from_date=datetime.now() - timedelta(days=30),
        to_date=datetime.now(),
    )
    
    print(f"Fetched {len(trades)} trades")
