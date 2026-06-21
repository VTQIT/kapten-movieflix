<?php
/**
 * StreamFlix - Authentication Handler
 */
require_once __DIR__ . '/functions.php';

class Auth {

    public static function register($username, $email, $password, $confirmPassword) {
        $errors = [];

        // Validation
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be between 3 and 50 characters';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        if (empty($password) || strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }

        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        // Check if username/email exists
        $existing = db()->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            return ['success' => false, 'errors' => ['Username or email already exists']];
        }

        // Check if registration is enabled
        if (!getSetting('registration_enabled', true)) {
            return ['success' => false, 'errors' => ['Registration is currently disabled']];
        }

        // Hash password and create user
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $storageLimit = getSetting('default_storage', 10737418240);

        $userId = db()->insert(
            "INSERT INTO users (username, email, password, storage_limit) VALUES (?, ?, ?, ?)",
            [$username, $email, $hashedPassword, $storageLimit]
        );

        if ($userId) {
            self::loginUser($userId);
            return ['success' => true, 'user_id' => $userId];
        }

        return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
    }

    public static function login($usernameOrEmail, $password, $remember = false) {
        $user = db()->fetch(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'",
            [$usernameOrEmail, $usernameOrEmail]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid credentials or account is suspended'];
        }

        // Update last login
        db()->update("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        self::loginUser($user['id']);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + 30 * 24 * 60 * 60, '/', '', false, true);
            db()->update("UPDATE users SET remember_token = ? WHERE id = ?", [$token, $user['id']]);
        }

        return ['success' => true];
    }

    public static function loginUser($userId) {
        $user = db()->fetch("SELECT id, username, email, role, avatar, status FROM users WHERE id = ?", [$userId]);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['avatar'] = $user['avatar'];
        }
    }

    public static function logout() {
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
            if (isset($_SESSION['user_id'])) {
                db()->update("UPDATE users SET remember_token = NULL WHERE id = ?", [$_SESSION['user_id']]);
            }
        }
        session_destroy();
    }

    public static function checkRememberToken() {
        if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
            $user = db()->fetch("SELECT id FROM users WHERE remember_token = ? AND status = 'active'", [$_COOKIE['remember_token']]);
            if ($user) {
                self::loginUser($user['id']);
            }
        }
    }

    public static function changePassword($userId, $currentPassword, $newPassword, $confirmPassword) {
        $user = db()->fetch("SELECT password FROM users WHERE id = ?", [$userId]);

        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }

        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'error' => 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'error' => 'New passwords do not match'];
        }

        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        db()->update("UPDATE users SET password = ? WHERE id = ?", [$hashed, $userId]);

        return ['success' => true];
    }

    public static function resetPassword($userId, $newPassword) {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        db()->update("UPDATE users SET password = ?, remember_token = NULL WHERE id = ?", [$hashed, $userId]);
        return true;
    }
}

// Check remember token on page load
Auth::checkRememberToken();
?>
