# 🚀 Quick Start Guide - Admin Panel

## Step 1: Run SQL (One Time Setup)

1. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Select database: **trading_db**
3. Go to **SQL** tab
4. Copy and paste this:

```sql
USE `trading_db`;

-- Create contacts table
CREATE TABLE IF NOT EXISTS `contacts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('contact', 'feedback') DEFAULT 'contact',
    `status` ENUM('pending', 'read', 'replied') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `balance`) 
VALUES ('admin', 'admin@npltrader.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0.00)
ON DUPLICATE KEY UPDATE `username`=`username`;

-- OR make your existing user admin (replace email):
-- UPDATE `users` SET `role` = 'admin' WHERE `email` = 'your-email@example.com';
```

5. Click **Go**

## Step 2: Login

1. Go to: `http://localhost/Trading_Site/login.php`
2. Login with:
   - **Username:** `admin`
   - **Password:** `admin123`

## Step 3: Access Admin Panel

**Method 1:** From Dashboard
- After login, go to Dashboard
- Click **"Admin Panel"** button in sidebar

**Method 2:** Direct URL
- Go to: `http://localhost/Trading_Site/admin/dashboard.php`

## ✅ Done!

You can now:
- Manage users
- Manage courses
- Manage blogs
- View payments
- View contacts
- View all trades

---

**⚠️ Important:** Change the admin password after first login!

