# Real/Demo Dashboard System

## Overview
यो system ले users लाई Real र Demo trading data लाई completely separate राख्न अनुमति दिन्छ। Users ले dashboard मा switch गरेर Real वा Demo mode select गर्न सक्छन्।

## Features

### 1. **Database Schema**
- `trading_accounts` table मा `is_demo` column (0 = Real, 1 = Demo)
- `trading_journal` table मा `is_demo` column (0 = Real, 1 = Demo)
- SQL migration file: `database/demo_real_dashboard_setup.sql`

### 2. **Mode Switching**
- Session-based mode management (`dashboard_mode.php`)
- Default mode: Real (0)
- Easy switching via dashboard UI

### 3. **Updated Pages**
- ✅ `dashboard.php` - Main dashboard with mode switcher
- ✅ `accounts.php` - Account management (filtered by mode)
- ✅ `journal.php` - Trading journal (filtered by mode)
- ✅ `portfolio.php` - Portfolio analytics (filtered by mode)
- ✅ `mt5_history.php` - MT5 history (filtered by mode)

### 4. **Visual Indicators**
- Mode badge (Real/Demo) in headers
- Sidebar mode indicator
- Color-coded buttons (Green for Real, Yellow for Demo)

## Setup Instructions

### Step 1: Database Migration
```sql
-- phpMyAdmin मा यो file import गर्नुहोस्:
database/demo_real_dashboard_setup.sql
```

### Step 2: Test the System
1. Dashboard मा जानुहोस्
2. Top मा "Real Dashboard" वा "Demo Dashboard" button click गर्नुहोस्
3. Mode switch हुन्छ र सबै data filter हुन्छ

## How It Works

### Mode Switching
- User clicks "Real Dashboard" वा "Demo Dashboard" button
- Session variable `$_SESSION['dashboard_mode']` set हुन्छ (0 = Real, 1 = Demo)
- सबै queries automatically filter by `is_demo` column

### Account Creation
- जब user account create गर्छ, current mode अनुसार `is_demo` automatically set हुन्छ
- Real mode मा: `is_demo = 0`
- Demo mode मा: `is_demo = 1`

### Journal Entries
- Journal entries create गर्दा current mode अनुसार `is_demo` set हुन्छ
- Real trades Real mode मा देखिन्छन्
- Demo trades Demo mode मा देखिन्छन्

## Files Modified

1. **New Files:**
   - `dashboard/dashboard_mode.php` - Mode utility functions
   - `database/demo_real_dashboard_setup.sql` - Database migration

2. **Updated Files:**
   - `dashboard/dashboard.php` - Mode switcher UI + filtered queries
   - `dashboard/accounts.php` - Mode filtering + account creation
   - `dashboard/journal.php` - Mode filtering + journal entry creation
   - `dashboard/portfolio.php` - Mode filtering for analytics
   - `dashboard/mt5_history.php` - Mode filtering for MT5 accounts

## Usage

### For Users:
1. Dashboard मा जानुहोस्
2. Mode switcher देख्नुहोस् (top मा)
3. "Real Dashboard" वा "Demo Dashboard" select गर्नुहोस्
4. सबै data automatically filter हुन्छ

### For Developers:
```php
// Check current mode
require_once __DIR__.'/dashboard_mode.php';
$is_demo = is_demo_mode(); // true if Demo, false if Real
$mode_name = get_mode_name(); // "Real" or "Demo"

// Get mode badge HTML
echo get_mode_badge(); // <span class="badge">...</span>

// Filter queries
$stmt = $pdo->prepare("SELECT * FROM trading_accounts WHERE user_id = ? AND is_demo = ?");
$stmt->execute([$user_id, $is_demo ? 1 : 0]);
```

## Notes

- Existing data: सबै existing data automatically Real (0) मा mark हुन्छ
- Mode persistence: Mode session मा store हुन्छ, logout गर्दा reset हुन्छ
- Design: Same design maintained, only mode indicators added

## Future Enhancements

- [ ] Mode-specific analytics
- [ ] Mode comparison view
- [ ] Export Real/Demo data separately
- [ ] Mode-specific reports
