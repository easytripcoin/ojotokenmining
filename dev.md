# OjoTokenMining Development Plan

## Project Structure

```
ojotokenmining/
├── config/
│   ├── database.php
│   ├── config.php
│   └── session.php
├── includes/
│   ├── functions.php
│   ├── auth.php
│   ├── validation.php
│   └── constants.php
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css
│   │   ├── admin.css
│   │   └── custom.css
│   ├── js/
│   │   ├── bootstrap.bundle.min.js
│   │   ├── d3.min.js
│   │   ├── genealogy.js
│   │   └── dashboard.js
│   └── images/
├── sql/
│   └── database_schema.sql
├── user/
│   ├── dashboard.php
│   ├── packages.php
│   ├── checkout.php
│   ├── ewallet.php
│   ├── withdrawal.php
│   ├── refill.php
│   ├── genealogy.php
│   └── profile.php
├── admin/
│   ├── dashboard.php
│   ├── users.php
│   ├── packages.php
│   ├── withdrawals.php
│   ├── refills.php
│   ├── settings.php
│   └── genealogy.php
├── api/
│   ├── genealogy_data.php
│   ├── monthly_bonus.php
│   └── referral_bonus.php
├── cron/
│   └── monthly_bonus_processor.php
├── index.php
├── login.php
├── register.php
├── logout.php
└── README.md
```

## Development Phases

### Phase 1: Core Authentication & Database Setup
**Goal**: Basic login/registration system with database foundation

#### Files to Create:

**config/database.php**
- Database connection configuration
- PDO connection with error handling
- Database connection testing

**config/config.php**
- System-wide constants and settings
- Package pricing configuration
- Bonus percentages configuration
- Admin settings

**config/session.php**
- Session management functions
- Session security settings
- User session validation

**sql/database_schema.sql**
- Complete database schema including:
  - users table (id, username, email, password, sponsor_id, created_at, status)
  - packages table (id, name, price, status)
  - user_packages table (id, user_id, package_id, purchase_date, status, current_cycle)
  - ewallet table (id, user_id, balance, created_at, updated_at)
  - ewallet_transactions table (id, user_id, type, amount, description, status, created_at)
  - withdrawal_requests table (id, user_id, amount, usdt_amount, wallet_address, status, created_at)
  - refill_requests table (id, user_id, amount, status, created_at, approved_at)
  - monthly_bonuses table (id, user_id, package_id, month, amount, status, created_at)
  - referral_bonuses table (id, user_id, referred_user_id, level, amount, created_at)
  - admin_settings table (id, setting_name, setting_value, updated_at)

**includes/functions.php**
- Common utility functions
- Password hashing/verification
- Input sanitization
- Date/time helpers

**includes/auth.php**
- User authentication functions
- Login validation
- Registration processing
- Session management

**includes/validation.php**
- Form validation functions
- Email validation
- Username validation
- Password strength validation

**login.php**
- Bootstrap login form
- Login processing
- Redirect to dashboard on success
- Error handling and display

**register.php**
- Bootstrap registration form
- User registration processing
- Sponsor validation
- Redirect to dashboard on success

**index.php**
- Landing page that redirects to login if not authenticated
- Redirects to dashboard if already logged in

**logout.php**
- Session destruction
- Redirect to login page

#### Phase 1 Deliverables:
- Working login/registration system
- Database schema implemented
- Basic session management
- User can register and login successfully

### Phase 2: User Dashboard Foundation & Package System
**Goal**: Core user dashboard with package selection and purchase

#### Files to Create:

**user/dashboard.php**
- Bootstrap admin template sidebar layout
- Dashboard overview with stats
- Package selection interface
- Monthly bonus display (placeholder)
- Navigation to other sections

**user/packages.php**
- Display all available packages
- Package selection form
- Redirect to checkout

**user/checkout.php**
- Package purchase confirmation
- Ewallet balance check
- Purchase processing or insufficient funds message
- Transaction recording

**assets/css/admin.css**
- Custom CSS for admin dashboard layout
- Sidebar styling
- Dashboard card styling
- Responsive design

**assets/js/dashboard.js**
- Dashboard interactivity
- AJAX calls for dynamic content
- Form validation

#### Updated Files:
**includes/functions.php**
- Add package management functions
- Purchase processing functions
- Ewallet balance checking

#### Phase 2 Deliverables:
- Functional user dashboard with sidebar navigation
- Package selection and purchase system
- Ewallet balance checking
- Transaction recording

### Phase 3: Ewallet System
**Goal**: Complete ewallet management with withdrawal and refill functionality

#### Files to Create:

**user/ewallet.php**
- Ewallet balance display
- Transaction history
- Quick access to withdrawal/refill

**user/withdrawal.php**
- Withdrawal request form
- Amount input with USDT conversion
- USDT wallet address input
- Request submission

**user/refill.php**
- Refill request form
- Admin USDT wallet address display
- Amount input
- Request submission

#### Updated Files:
**includes/functions.php**
- Add ewallet transaction functions
- Withdrawal request processing
- Refill request processing
- Balance calculation functions

#### Phase 3 Deliverables:
- Complete ewallet system
- Withdrawal request functionality
- Refill request functionality
- Transaction history tracking

### Phase 4: Admin Panel
**Goal**: Admin dashboard for managing users, requests, and system settings

#### Files to Create:

**admin/dashboard.php**
- Admin dashboard overview
- System statistics
- Pending requests summary

**admin/users.php**
- User management interface
- User search and filtering
- User package history

**admin/withdrawals.php**
- Withdrawal requests management
- Approve/reject functionality
- Request details view

**admin/refills.php**
- Refill requests management
- Approve/reject functionality
- Request tracking

**admin/settings.php**
- Package pricing configuration
- Bonus percentage settings
- System configuration

**admin/packages.php**
- Package management
- Add/edit/disable packages
- Package statistics

#### Updated Files:
**includes/auth.php**
- Add admin authentication checks
- Admin role validation

**includes/functions.php**
- Add admin management functions
- Request approval/rejection functions
- Settings update functions

#### Phase 4 Deliverables:
- Complete admin panel
- Request management system
- System settings configuration
- User management tools

### Phase 5: Monthly Bonus System
**Goal**: Automated monthly bonus calculation and processing

#### Files to Create:

**cron/monthly_bonus_processor.php**
- Automated monthly bonus calculation
- 50% bonus distribution for 3 months
- 4th month withdraw/remine button activation

**api/monthly_bonus.php**
- API endpoint for monthly bonus data
- Bonus history retrieval
- Current bonus status

#### Updated Files:
**user/dashboard.php**
- Add monthly bonus tracking
- Withdraw/Remine buttons (4th month)
- Bonus history display

**includes/functions.php**
- Monthly bonus calculation functions
- Withdraw/remine processing
- Bonus distribution logic

#### Phase 5 Deliverables:
- Automated monthly bonus system
- Withdraw/Remine functionality
- Bonus tracking and history

### Phase 6: Referral System
**Goal**: Multi-level referral bonus system

#### Files to Create:

**api/referral_bonus.php**
- API endpoint for referral data
- Referral tree calculation
- Bonus distribution

#### Updated Files:
**includes/functions.php**
- Referral tree building functions
- Multi-level bonus calculation (10% 2nd level, 1% 3rd-5th level)
- Referral bonus distribution

**user/dashboard.php**
- Add referral bonus display
- Referral statistics

#### Phase 6 Deliverables:
- Multi-level referral system
- Automated referral bonus calculation
- Referral statistics and tracking

### Phase 7: Genealogy Visualization
**Goal**: Interactive D3.js genealogy tree with advanced features

#### Files to Create:

**user/genealogy.php**
- Genealogy tree container
- D3.js integration
- Tree controls and filters

**assets/js/genealogy.js**
- D3.js tree visualization
- Collapsing/expanding nodes
- Pan and zoom functionality
- Node tooltips with full usernames
- Truncated username display (10 chars)
- Referral bonus display per node

**api/genealogy_data.php**
- API endpoint for genealogy tree data
- Hierarchical user data
- Referral bonus information per user

#### Updated Files:
**assets/css/custom.css**
- Genealogy tree styling
- Node and link styling
- Tooltip styling

#### Phase 7 Deliverables:
- Interactive genealogy tree
- Advanced D3.js features (pan, zoom, collapse)
- Hover tooltips with detailed information
- Visual referral bonus display

### Phase 8: System Optimization & Security
**Goal**: Performance optimization, security hardening, and final testing

#### Tasks:
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- CSRF token implementation
- Password security enhancements
- Database query optimization
- Code review and refactoring
- Comprehensive testing
- Documentation completion

#### Updated Files:
**All PHP files**
- Security enhancements
- Input validation improvements
- Error handling improvements

**includes/validation.php**
- Enhanced validation functions
- Security-focused validation

#### Phase 8 Deliverables:
- Secure, production-ready system
- Optimized performance
- Comprehensive documentation
- Full system testing

## Key Features Summary:

1. **Authentication System**: Secure login/registration with sponsor referrals
2. **Package System**: 6-tier package structure with automated processing
3. **Ewallet System**: Complete wallet management with USDT integration
4. **Monthly Bonus System**: 50% monthly bonuses for 3 months, then withdraw/remine options
5. **Referral System**: Multi-level bonuses (10% 2nd level, 1% 3rd-5th level)
6. **Admin Panel**: Complete administrative control
7. **Genealogy Tree**: Interactive D3.js visualization with advanced features
8. **Security**: Industry-standard security practices

## Technology Stack:
- **Backend**: Pure PHP (procedural)
- **Database**: MySQL
- **Frontend**: Bootstrap 5, HTML5, CSS3
- **JavaScript**: Vanilla JS, D3.js
- **Currency**: USDT integration
- **Architecture**: MVC-inspired structure without frameworks

Each phase builds upon the previous one, ensuring a stable, testable system at every stage of development.