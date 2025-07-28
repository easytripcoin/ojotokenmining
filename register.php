<?php
// Register Page
// register.php

require_once 'config/config.php';
require_once 'config/session.php';
require_once 'includes/auth.php';
require_once 'includes/validation.php';

// Prevent logged in users from accessing register page
preventLoggedInAccess();

$errors = [];
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token. Please try again.';
    } else {
        // Validate registration data
        $validation = validateRegistrationData($_POST);

        if ($validation['valid']) {
            // Attempt registration
            $result = registerUser($validation['data']);

            if ($result['success']) {
                redirectWithMessage('login.php', $result['message'], 'success');
            } else {
                $errors['general'] = $result['message'];
            }
        } else {
            $errors = $validation['errors'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .input-group-text {
            background: transparent;
            border-right: none;
        }

        .form-control {
            border-left: none;
        }

        .password-strength {
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background-color: #dc3545;
        }

        .strength-fair {
            background-color: #ffc107;
        }

        .strength-good {
            background-color: #20c997;
        }

        .strength-strong {
            background-color: #28a745;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-container">
                    <!-- Header -->
                    <div class="register-header text-center py-4">
                        <h3 class="mb-0">
                            <i class="fas fa-coins me-2"></i>
                            <?php echo SITE_NAME; ?>
                        </h3>
                        <p class="mb-0 mt-2">Create Your Account</p>
                    </div>

                    <!-- Body -->
                    <div class="p-4">
                        <!-- General Error -->
                        <?php if (isset($errors['general'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Registration Form -->
                        <form method="POST" action="" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <div class="row">
                                <!-- Username -->
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                                            id="username" name="username"
                                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                            placeholder="Choose a username" required>
                                        <?php if (isset($errors['username'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['username']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">3-50 characters, letters, numbers, and underscores
                                        only</small>
                                </div>

                                <!-- Email -->
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email"
                                            class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                            id="email" name="email"
                                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                            placeholder="Enter your email" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Password -->
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password"
                                            class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                            id="password" name="password" placeholder="Enter password" required>
                                        <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (isset($errors['password'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['password']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="password-strength mt-1" id="passwordStrength"></div>
                                    <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?>
                                        characters</small>
                                </div>

                                <!-- Confirm Password -->
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password"
                                            class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                            id="confirm_password" name="confirm_password" placeholder="Confirm password"
                                            required>
                                        <button type="button" class="btn btn-outline-secondary"
                                            id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback">
                                                <?php echo htmlspecialchars($errors['confirm_password']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div id="passwordMatch" class="mt-1"></div>
                                </div>
                            </div>

                            <!-- Sponsor Name -->
                            <div class="mb-4">
                                <label for="sponsor_name" class="form-label">Sponsor Username (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user-friends"></i>
                                    </span>
                                    <input type="text"
                                        class="form-control <?php echo isset($errors['sponsor_name']) ? 'is-invalid' : ''; ?>"
                                        id="sponsor_name" name="sponsor_name"
                                        value="<?php echo htmlspecialchars($_POST['sponsor_name'] ?? ''); ?>"
                                        placeholder="Enter sponsor's username (optional)">
                                    <?php if (isset($errors['sponsor_name'])): ?>
                                        <div class="invalid-feedback">
                                            <?php echo htmlspecialchars($errors['sponsor_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Leave blank to be automatically assigned to admin as sponsor
                                </small>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-register">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Create Account
                                </button>
                            </div>
                        </form>

                        <!-- Login Link -->
                        <div class="text-center">
                            <p class="mb-0">Already have an account?
                                <a href="login.php" class="text-decoration-none fw-bold">
                                    Login here <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePasswordVisibility(passwordId, toggleButtonId) {
            const password = document.getElementById(passwordId);
            const toggleButton = document.getElementById(toggleButtonId);
            const icon = toggleButton.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('togglePassword').addEventListener('click', function () {
            togglePasswordVisibility('password', 'togglePassword');
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
            togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');
        });

        // Password strength indicator
        document.getElementById('password').addEventListener('input', function () {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');

            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength';
                return;
            }

            let strength = 0;
            const checks = [
                password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>,
                /[a-z]/.test(password),
                /[A-Z]/.test(password),
                /\d/.test(password),
                /[^a-zA-Z\d]/.test(password)
            ];

            strength = checks.filter(check => check).length;

            const strengthClasses = ['', 'strength-weak', 'strength-fair', 'strength-good', 'strength-strong'];
            const strengthWidths = ['0%', '20%', '40%', '60%', '80%', '100%'];

            strengthBar.style.width = strengthWidths[strength];
            strengthBar.className = 'password-strength ' + (strengthClasses[strength] || '');
        });

        // Password match indicator
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchIndicator = document.getElementById('passwordMatch');

            if (confirmPassword.length === 0) {
                matchIndicator.innerHTML = '';
                return;
            }

            if (password === confirmPassword) {
                matchIndicator.innerHTML = '<small class="text-success"><i class="fas fa-check me-1"></i>Passwords match</small>';
            } else {
                matchIndicator.innerHTML = '<small class="text-danger"><i class="fas fa-times me-1"></i>Passwords do not match</small>';
            }
        }

        document.getElementById('password').addEventListener('input', checkPasswordMatch);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function (e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                e.preventDefault();
                alert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long!');
                return false;
            }
        });
    </script>
</body>

</html>