You can generate the password hash manually and update it directly in the database. Here's how to do it:## Instructions:

1. **Create the file `generate_hash.php`** in your root directory (same level as `login.php`)

2. **Run the file** by visiting `http://localhost/ojotokenmining/generate_hash.php` in your browser

3. **Copy the generated SQL command** and run it in your database

### Alternative - Direct Database Update:

If you want to quickly update the admin password to `admin123`, run this SQL command directly in your database:

```sql
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';
```

### Or create a new admin user:

```sql
INSERT INTO users (username, email, password, role, status) VALUES 
('admin', 'admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');
```

### Common Test Passwords & Their Hashes:

- **Password:** `admin123`
- **Hash:** `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`

- **Password:** `password123` 
- **Hash:** `$2y$10$EzvKSVGJUQp85wSiJTRhMu5d8i5J9R0wLkKOKx5Oy2mOp0oWVfDAu`

### Debugging Login Issues:

If you're still having login issues after updating the password hash, add this debug code to your `login.php` file temporarily (remove after testing):

```php
// Add this after the authenticateUser call in login.php
if (!$user) {
    // Debug information
    echo "<pre>";
    echo "Login attempt failed\n";
    echo "Username: " . htmlspecialchars($username) . "\n";
    echo "Password: " . htmlspecialchars($password) . "\n";
    
    // Check if user exists
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT username, password FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $debug_user = $stmt->fetch();
    
    if ($debug_user) {
        echo "User found in database\n";
        echo "DB Password Hash: " . $debug_user['password'] . "\n";
        echo "Hash verification: " . (password_verify($password, $debug_user['password']) ? 'SUCCESS' : 'FAILED') . "\n";
    } else {
        echo "User NOT found in database\n";
    }
    echo "</pre>";
}
```

Use the hash generator file to create a fresh password hash and update your database. This should resolve the login issue!