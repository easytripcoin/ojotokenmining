-- ===================================================
-- OjoTokenMining - Complete Database Reset
-- Includes all tables from Phase 1-6
-- ===================================================

-- Drop database if exists
DROP DATABASE IF EXISTS ojotokenmining;

-- Create database
CREATE DATABASE IF NOT EXISTS ojotokenmining CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ojotokenmining;

-- ===================================================
-- CORE TABLES
-- ===================================================

-- Users table
DROP TABLE IF EXISTS users;

CREATE TABLE IF NOT EXISTS users (
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
DROP TABLE IF EXISTS packages;

CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    description TEXT NULL,
    features TEXT NULL,
    order_index INT DEFAULT 0,
    image_path VARCHAR(255) NULL,
    referral_bonus_enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User packages (purchases)
DROP TABLE IF EXISTS user_packages;

CREATE TABLE IF NOT EXISTS user_packages (
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

-- ===================================================
-- E-WALLET SYSTEM
-- ===================================================

DROP TABLE IF EXISTS ewallet;

CREATE TABLE IF NOT EXISTS ewallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

DROP TABLE IF EXISTS ewallet_transactions;

CREATE TABLE IF NOT EXISTS ewallet_transactions (
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

-- ===================================================
-- REQUESTS SYSTEM
-- ===================================================

DROP TABLE IF EXISTS withdrawal_requests;

CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
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

DROP TABLE IF EXISTS refill_requests;

CREATE TABLE IF NOT EXISTS refill_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_hash VARCHAR(255) NULL,
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

-- ===================================================
-- BONUS SYSTEM
-- ===================================================

DROP TABLE IF EXISTS monthly_bonuses;

CREATE TABLE IF NOT EXISTS monthly_bonuses (
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
    ) DEFAULT 'paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE,
    FOREIGN KEY (user_package_id) REFERENCES user_packages (id) ON DELETE CASCADE
);

DROP TABLE IF EXISTS referral_bonuses;

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

-- ===================================================
-- ADMIN SYSTEM
-- ===================================================

DROP TABLE IF EXISTS admin_settings;

CREATE TABLE IF NOT EXISTS admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ===================================================
-- DEFAULT DATA
-- ===================================================

-- Insert default packages
INSERT INTO
    packages (
        name,
        price,
        description,
        features
    )
VALUES (
        'Starter Plan',
        20.00,
        'Perfect for beginners to start earning',
        '• 20 USDT minimum\n• 50% monthly bonus\n• 3-month cycle\n• Referral bonuses'
    ),
    (
        'Bronze Plan',
        100.00,
        'Good starting investment',
        '• 100 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Multi-level referrals'
    ),
    (
        'Silver Plan',
        500.00,
        'Balanced investment option',
        '• 500 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Advanced features'
    ),
    (
        'Gold Plan',
        1000.00,
        'Premium investment package',
        '• 1000 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Priority support'
    ),
    (
        'Platinum Plan',
        2000.00,
        'High-value investment',
        '• 2000 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• VIP features'
    ),
    (
        'Diamond Plan',
        10000.00,
        'Ultimate investment',
        '• 10000 USDT package\n• 50% monthly bonus\n• 3-month cycle\n• Exclusive benefits'
    );

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
        'TAdminUSDTWalletAddressHere12345',
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

-- Reset complete message
SELECT 'Database reset complete for OjoTokenMining' AS status;