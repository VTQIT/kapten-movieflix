<?php
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $usernameOrEmail = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        $result = Auth::login($usernameOrEmail, $password, $remember);

        if ($result['success']) {
            $redirect = $_SESSION['redirect_after_login'] ?? SITE_URL . '/index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Welcome Back</h1>
                <p>Sign in to continue watching</p>
            </div>

            <?php if ($error): ?>
                <div class="flash-message flash-error">
                    <i data-lucide="x-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <i data-lucide="user"></i>
                        <input type="text" id="username" name="username" required 
                               placeholder="Enter your username or email"
                               value="<?php echo isset($_POST['username']) ? sanitizeInput($_POST['username']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i data-lucide="lock"></i>
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i data-lucide="log-in"></i>
                    Sign In
                </button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="<?php echo SITE_URL; ?>/register.php">Get Started</a></p>
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
