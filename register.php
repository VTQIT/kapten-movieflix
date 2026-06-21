<?php
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $result = Auth::register($username, $email, $password, $confirmPassword);

        if ($result['success']) {
            setFlash('success', 'Welcome to ' . SITE_NAME . '! Your account has been created.');
            header('Location: ' . SITE_URL . '/index.php');
            exit;
        } else {
            $errors = $result['errors'];
        }
    }
}

$pageTitle = 'Get Started';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Create Account</h1>
                <p>Join our community of creators</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="flash-message flash-error">
                    <i data-lucide="x-circle"></i>
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo $err; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <i data-lucide="user"></i>
                        <input type="text" id="username" name="username" required 
                               placeholder="Choose a username"
                               value="<?php echo isset($_POST['username']) ? sanitizeInput($_POST['username']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-group">
                        <i data-lucide="mail"></i>
                        <input type="email" id="email" name="email" required 
                               placeholder="your@email.com"
                               value="<?php echo isset($_POST['email']) ? sanitizeInput($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i data-lucide="lock"></i>
                        <input type="password" id="password" name="password" required 
                               placeholder="Create a password" minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                    <span class="form-hint">At least 6 characters</span>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-group">
                        <i data-lucide="lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm your password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i data-lucide="user-plus"></i>
                    Create Account
                </button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="<?php echo SITE_URL; ?>/login.php">Sign In</a></p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
