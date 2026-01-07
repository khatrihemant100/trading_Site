# Admin Panel Setup Guide

## 📋 Requirements
- PHP 7.4 or higher
- MySQL/MariaDB database
- XAMPP/WAMP/LAMP server running

## 🚀 Setup Instructions

### Step 1: Database Setup

1. **Open phpMyAdmin** (usually at `http://localhost/phpmyadmin`)

2. **Select your database** (`trading_db`)

3. **Import SQL file:**
   - Go to **Import** tab
   - Click **Choose File**
   - Select: `database/admin_panel_setup.sql`
   - Click **Go**

   **OR** manually run the SQL:
   - Go to **SQL** tab
   - Copy and paste contents from `database/admin_panel_setup.sql`
   - Click **Go**

### Step 2: Create Admin User

**Option A: Use Default Admin Account**
- Username: `admin`
- Email: `admin@npltrader.com`
- Password: `admin123`
- ⚠️ **Important:** Change password after first login!

**Option B: Make Existing User Admin**
Run this SQL in phpMyAdmin:
```sql
UPDATE `users` SET `role` = 'admin' WHERE `email` = 'your-email@example.com';
```

**Option C: Create New Admin User**
1. Register normally on the website
2. Then run this SQL:
```sql
UPDATE `users` SET `role` = 'admin' WHERE `email` = 'your-registered-email@example.com';
```

### Step 3: Access Admin Panel

1. **Login to website** with admin account
2. **Go to Dashboard** (`dashboard/dashboard.php`)
3. **Click "Admin Panel"** button in sidebar
   OR
4. **Direct URL:** `http://localhost/Trading_Site/admin/dashboard.php`

## 🔐 Admin Panel Features

### Dashboard
- Overview statistics
- Recent activities
- Quick access to all sections

### User Management
- View all users
- Change user roles (User/Admin)
- Delete users
- Search and filter users

### Course Management
- Add/Edit/Delete courses
- Set prices and levels
- Manage course status

### Blog Management
- Create/Edit/Delete blog posts
- Publish/Draft posts
- View blog statistics

### Payment Management
- View all payments
- Update payment status
- Revenue tracking
- Filter by status

### Contact & Feedback
- View all contacts/feedback
- Update status (Pending/Read/Replied)
- Delete messages

### Trading Journal Overview
- View all trades from all users
- Filter by user, symbol, date
- Trading statistics

## 🛡️ Security Notes

1. **Change Default Password:**
   - Login as admin
   - Go to Profile
   - Change password immediately

2. **Admin Access:**
   - Only users with `role = 'admin'` can access
   - Non-admin users will be redirected

3. **Database Security:**
   - Keep database credentials secure
   - Don't commit `config/database.php` to public repos

## 📁 File Structure

```
admin/
├── auth.php          # Authentication check
├── dashboard.php     # Main dashboard
├── users.php         # User management
├── courses.php       # Course management
├── blogs.php         # Blog management
├── payments.php      # Payment management
├── contacts.php      # Contact management
├── trades.php        # Trading journal overview
├── sidebar.php       # Navigation sidebar
├── styles.php        # Common styles
└── README.md         # This file
```

## ❓ Troubleshooting

### "Access Denied" Error
- Check if user role is 'admin' in database
- Verify session is active
- Try logging out and logging back in

### Tables Not Found
- Run `database/admin_panel_setup.sql`
- Check database name in `config/database.php`

### Can't Login
- Verify admin user exists: `SELECT * FROM users WHERE role = 'admin';`
- Check password hash is correct
- Try resetting password

## 📞 Support

If you encounter any issues:
1. Check database connection in `config/database.php`
2. Verify all tables exist
3. Check PHP error logs
4. Ensure session is working

---

**Note:** Always backup your database before running SQL scripts!

