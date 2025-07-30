-- sql/database_schema.sql
-- OjoTokenMining Database Schema
-- Phase 1: Core tables for authentication and system foundation

DROP DATABASE IF EXISTS ojotokenmining;

CREATE DATABASE IF NOT EXISTS ojotokenmining;

USE ojotokenmining;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    sponsor_id INT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM(
        'active',
        'inactive',
        'suspended'
    ) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sponsor_id) REFERENCES users (id) ON DELETE SET NULL
);

-- Packages table
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User packages (purchases)
CREATE TABLE user_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    current_cycle INT DEFAULT 1,
    total_cycles INT DEFAULT 3,
    status ENUM(
        'active',
        'completed',
        'withdrawn'
    ) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
);

-- Ewallet system
CREATE TABLE ewallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Ewallet transactions
CREATE TABLE ewallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM(
        'deposit',
        'withdrawal',
        'bonus',
        'referral',
        'purchase',
        'refund'
    ) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description TEXT,
    status ENUM(
        'pending',
        'completed',
        'failed'
    ) DEFAULT 'completed',
    reference_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Withdrawal requests
CREATE TABLE withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    usdt_amount DECIMAL(15, 8) NOT NULL,
    wallet_address VARCHAR(255) NOT NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'completed'
    ) DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Refill requests
CREATE TABLE refill_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected'
    ) DEFAULT 'pending',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Monthly bonuses tracking
CREATE TABLE monthly_bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    user_package_id INT NOT NULL,
    month_number INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    status ENUM(
        'pending',
        'paid',
        'withdrawn'
    ) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    FOREIGN KEY (user_package_id) REFERENCES user_packages (id) ON DELETE CASCADE
);

-- Referral bonuses
CREATE TABLE IF NOT EXISTS referral_bonuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    level INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    percentage DECIMAL(5, 2) NOT NULL,
    package_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
);

-- Admin settings
CREATE TABLE admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default packages
INSERT INTO
    packages (name, price)
VALUES ('Starter Plan', 20.00),
    ('Bronze Plan', 100.00),
    ('Silver Plan', 500.00),
    ('Gold Plan', 1000.00),
    ('Platinum Plan', 2000.00),
    ('Diamond Plan', 10000.00);

-- Insert default admin settings
INSERT INTO
    admin_settings (
        setting_name,
        setting_value,
        description
    )
VALUES (
        'monthly_bonus_percentage',
        '50',
        'Monthly bonus percentage'
    ),
    (
        'referral_level_2_percentage',
        '10',
        'Level 2 referral bonus percentage'
    ),
    (
        'referral_level_3_percentage',
        '1',
        'Level 3 referral bonus percentage'
    ),
    (
        'referral_level_4_percentage',
        '1',
        'Level 4 referral bonus percentage'
    ),
    (
        'referral_level_5_percentage',
        '1',
        'Level 5 referral bonus percentage'
    ),
    (
        'admin_usdt_wallet',
        'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXxx',
        'Admin USDT wallet address for refills'
    ),
    (
        'usdt_rate',
        '1.00',
        'USDT conversion rate'
    ),
    (
        'default_currency',
        'USDT',
        'Default currency for the system'
    ),
    (
        'default_sponsor_enabled',
        '1',
        'Enable automatic admin sponsor assignment'
    ),
    (
        'orphan_prevention',
        '1',
        'Prevent orphaned users by assigning default sponsor'
    );

-- Create default admin user (password: admin123)
INSERT INTO
    users (
        username,
        email,
        password,
        role
    )
VALUES (
        'admin',
        'admin@ojotokenmining.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin'
    );

-- Create ewallet for admin user
INSERT INTO ewallet (user_id, balance) VALUES (1, 0.00);

-- Ensure packages table has all required columns
ALTER TABLE packages
ADD COLUMN description TEXT NULL AFTER price,
ADD COLUMN features TEXT NULL AFTER description,
ADD COLUMN order_index INT DEFAULT 0 AFTER features;

-- Add package images support
ALTER TABLE packages
ADD COLUMN image_path VARCHAR(255) NULL AFTER features;

-- Update packages table to include referral tracking
ALTER TABLE packages
ADD COLUMN IF NOT EXISTS referral_bonus_enabled TINYINT(1) DEFAULT 1;

-- Add transaction_hash to refill_requests
ALTER TABLE refill_requests
ADD COLUMN transaction_hash VARCHAR(255) NULL AFTER amount;

-- Update existing packages with descriptions
-- UPDATE packages SET
--   description = 'Perfect for beginners to start earning',
--   features = '• 20 USDT minimum\n• 50% monthly bonus\n• 3-month cycle\n• Referral bonuses',
--   order_index = 1
-- WHERE name = 'Starter Plan';

-- Repeat for other packages...

-- TRUNCATE TABLE packages;

-- SELECT * FROM users WHERE sponsor_id = 2;

-- SELECT * FROM referral_bonuses WHERE user_id = 2;

ALTER TABLE ewallet_transactions
ADD COLUMN is_withdrawable TINYINT(1) DEFAULT 0;

ALTER TABLE `ewallet_transactions`
MODIFY COLUMN `type` ENUM(
    'deposit',
    'withdrawal',
    'bonus',
    'referral',
    'purchase',
    'refund',
    'transfer',
    'transfer_charge'
) COLLATE utf8mb4_unicode_ci NOT NULL;