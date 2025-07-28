# OjoTokenMining System

A comprehensive multi-level marketing (MLM) platform built with pure PHP, featuring package-based mining, referral systems, ewallet management, and automated bonus distribution.

## 🚀 Features

### Core Functionality
- **User Authentication System**: Secure registration and login
- **6-Tier Package System**: Multiple mining packages with automated processing
- **Ewallet Management**: Complete wallet system with USDT integration
- **Monthly Bonus System**: 50% monthly bonuses for 3 months
- **Multi-Level Referral System**: 10% (2nd level), 1% (3rd-5th level) bonuses
- **Interactive Genealogy Tree**: D3.js-powered visualization
- **Admin Management Panel**: Complete administrative control
- **Automated Processing**: Cron jobs for bonus calculations

## 📋 System Requirements

- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache or Nginx
- **Extensions**: PDO, PDO_MySQL, mbstring, openssl

## 🛠️ Installation

### 1. Clone/Download the Project
```bash
git clone [repository-url]
cd ojotokenmining
```

### 2. Database Setup
```bash
# Import the database schema
mysql -u your_username -p your_database_name < sql/database_schema.sql
```

### 3. Configuration
Edit `config/database.php` with your database credentials:
```php
$host = 'localhost';
$dbname = 'your_database_name';
$username = 'your_username';
$password = 'your_password';
```

Update `config/config.php` for system settings:
```php
define('SITE_URL', 'https://yourdomain.com');
define('SITE_NAME', 'OjoTokenMining');
// Configure other settings as needed
```

### 4. Set Permissions
```bash
chmod 755 -R ojotokenmining/
chmod 644 config/*.php
```

### 5. Web Server Configuration
Point your web server's document root to the project directory.

## 📁 Project Structure

```
ojotokenmining/
├── config/                 # Configuration files
│   ├── database.php        # Database connection
│   ├── config.php          # System settings
│   └── session.php         # Session management
├── includes/               # Core PHP functions
│   ├── functions.php       # Utility functions
│   ├── auth.php           # Authentication functions
│   ├── validation.php     # Form validation
│   └── constants.php      # System constants
├── assets/                # Static assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   └── images/            # Image assets
├── user/                  # User dashboard pages
├── admin/                 # Admin panel pages
├── api/                   # API endpoints
├── cron/                  # Automated tasks
└── sql/                   # Database schema
```

## 👥 User Guide

### Getting Started

#### 1. Registration
- Visit the registration page
- Enter your details (username, email, password)
- **Important**: Use a valid sponsor username to join under someone
- Complete registration and login

#### 2. Dashboard Overview
After login, you'll see:
- **Account Balance**: Your current ewallet balance
- **Active Packages**: Currently active mining packages
- **Monthly Bonuses**: Bonus history and status
- **Referral Statistics**: Your referral network stats

#### 3. Package Selection
- Navigate to "Packages" from the sidebar
- Choose from 6 available packages:
  - **Package 1**: $10 - Basic mining package
  - **Package 2**: $50 - Standard mining package
  - **Package 3**: $100 - Premium mining package
  - **Package 4**: $500 - Professional mining package
  - **Package 5**: $1000 - Enterprise mining package
  - **Package 6**: $2000 - Elite mining package

#### 4. Ewallet Management
- **Balance Check**: View current USDT balance
- **Transaction History**: Track all wallet activities
- **Refill Wallet**: Add funds to your account
- **Withdraw Funds**: Request withdrawals to your USDT wallet

### Key Features Explained

#### Monthly Bonus System
- **Months 1-3**: Receive 50% of package value monthly
- **Month 4**: Choose to withdraw or remine (restart cycle)
- Bonuses are automatically calculated and distributed

#### Referral System
- **Direct Referrals**: Earn from users you directly refer
- **2nd Level**: 10% bonus from your referrals' referrals
- **3rd-5th Level**: 1% bonus from deeper levels
- Bonuses are automatically credited to your ewallet

#### Genealogy Tree
- Visual representation of your referral network
- Interactive D3.js tree with zoom and pan
- Click nodes to expand/collapse branches
- Hover for detailed user information

## 🔧 Administrator Guide

### Admin Access
Default admin credentials (change immediately):
- **Username**: admin
- **Password**: admin123

### Admin Functions

#### User Management
- View all registered users
- Search and filter users
- View user package history
- Monitor user activities

#### Request Management
- **Withdrawal Requests**: Approve/reject withdrawal requests
- **Refill Requests**: Approve fund refill requests
- Track request history and status

#### Package Management
- Configure package prices and features
- Enable/disable packages
- Monitor package statistics

#### System Settings
- Configure bonus percentages
- Set withdrawal limits
- Update system parameters
- Manage USDT conversion rates

#### Financial Overview
- Monitor total system funds
- Track bonus distributions
- Generate financial reports
- View platform statistics

## 💻 Developer Guide

### Architecture Overview
The system follows an MVC-inspired architecture without frameworks:
- **Models**: Database operations in `includes/functions.php`
- **Views**: PHP templates with Bootstrap styling
- **Controllers**: Page-specific logic in individual PHP files

### Database Schema

#### Core Tables
- `users`: User accounts and basic information
- `packages`: Available mining packages
- `user_packages`: User-package relationships
- `ewallet`: User wallet balances
- `ewallet_transactions`: All wallet transactions

#### Bonus Tables
- `monthly_bonuses`: Monthly bonus records
- `referral_bonuses`: Referral bonus tracking

#### Request Tables
- `withdrawal_requests`: Withdrawal processing
- `refill_requests`: Fund refill processing

### Key Functions

#### Authentication (`includes/auth.php`)
```php
// Login user
login_user($username, $password)

// Register new user
register_user($userData)

// Check if user is logged in
is_logged_in()

// Check admin privileges
is_admin()
```

#### Package Management (`includes/functions.php`)
```php
// Get all packages
get_all_packages()

// Purchase package
purchase_package($user_id, $package_id)

// Check user packages
get_user_packages($user_id)
```

#### Ewallet Operations
```php
// Get wallet balance
get_ewallet_balance($user_id)

// Add transaction
add_ewallet_transaction($user_id, $type, $amount, $description)

// Process withdrawal
process_withdrawal_request($request_id, $status)
```

### API Endpoints

#### Genealogy Data (`api/genealogy_data.php`)
```
GET /api/genealogy_data.php?user_id=123
Returns: JSON tree structure for D3.js visualization
```

#### Monthly Bonus (`api/monthly_bonus.php`)
```
GET /api/monthly_bonus.php?user_id=123
Returns: User's monthly bonus history and status
```

### Automated Tasks

#### Monthly Bonus Processor (`cron/monthly_bonus_processor.php`)
```bash
# Add to crontab for monthly execution
0 0 1 * * php /path/to/ojotokenmining/cron/monthly_bonus_processor.php
```

### Security Implementation

#### Input Validation
- All user inputs are sanitized using `htmlspecialchars()`
- SQL injection prevention with prepared statements
- CSRF protection on all forms

#### Password Security
- Passwords hashed using `password_hash()` with PASSWORD_DEFAULT
- Strong password requirements enforced

#### Session Management
- Secure session configuration
- Session timeout implementation
- Session regeneration on login

### Customization

#### Adding New Package Types
1. Insert new package in `packages` table
2. Update package display in `user/packages.php`
3. Modify bonus calculations if needed

#### Modifying Bonus Structure
1. Update bonus percentages in `config/config.php`
2. Modify calculation logic in bonus processing functions
3. Update display components accordingly

#### Styling Customization
- Main styles: `assets/css/admin.css`
- Bootstrap overrides: `assets/css/custom.css`
- Genealogy tree: Modify `assets/js/genealogy.js`

## 🔍 Troubleshooting

### Common Issues

#### Database Connection Errors
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database permissions

#### Login Issues
- Clear browser cache and cookies
- Verify user exists in database
- Check session configuration

#### Package Purchase Failures
- Verify ewallet balance
- Check package availability
- Review transaction logs

#### Bonus Calculation Issues
- Verify cron job execution
- Check monthly bonus processor logs
- Validate referral tree structure

### Debug Mode
Enable debug mode in `config/config.php`:
```php
define('DEBUG_MODE', true);
```

This will display detailed error messages and SQL queries.

## 📞 Support

### For Users
- Contact your sponsor for general questions
- Use the support ticket system (if implemented)
- Check the FAQ section

### For Developers
- Review code comments and documentation
- Check error logs in `/logs/` directory
- Use debug mode for troubleshooting

## 🔐 Security Notes

### Important Security Measures
1. **Change Default Passwords**: Immediately change default admin credentials
2. **SSL Certificate**: Always use HTTPS in production
3. **Database Security**: Use strong database passwords and limited privileges
4. **File Permissions**: Set appropriate file and directory permissions
5. **Regular Updates**: Keep PHP and MySQL updated

### Recommended Production Settings
```php
// In config/config.php
define('DEBUG_MODE', false);
define('DISPLAY_ERRORS', false);
define('LOG_ERRORS', true);
```

## 📈 Scalability Considerations

### Performance Optimization
- Implement database query optimization
- Use caching for frequently accessed data
- Optimize genealogy tree loading for large networks
- Consider CDN for static assets

### Growth Planning
- Monitor database size and performance
- Plan for increased transaction volume
- Consider load balancing for high traffic
- Implement backup and disaster recovery

## 📝 License

This project is proprietary software. All rights reserved.

## 🎯 Roadmap

### Planned Features
- Mobile application integration
- Advanced reporting dashboard
- Multi-currency support
- Enhanced security features
- API for third-party integrations

---

**Version**: 1.0.0  
**Last Updated**: July 2025  
**Maintainer**: Development Team

For technical support or contributions, please contact the development team.