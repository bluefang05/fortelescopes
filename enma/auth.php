<?php

declare(strict_types=1);

/**
 * Authentication handler for ENMA admin panel
 * Handles login, logout and session management
 */

if (!function_exists('enma_set_owner_tracking_cookie')) {
    function enma_set_owner_tracking_cookie(bool $enabled): void
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $expires = $enabled ? (time() + (86400 * 30)) : (time() - 3600);
        $value = $enabled ? '1' : '';

        if (PHP_VERSION_ID >= 70300) {
            setcookie('ft_owner_visit', $value, [
                'expires' => $expires,
                'path' => '/',
                'secure' => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return;
        }

        setcookie(
            'ft_owner_visit',
            $value,
            $expires,
            '/; samesite=Lax',
            '',
            $https,
            true
        );
    }
}

// Logout handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        enma_record_activity($pdo, 'auth.logout', 'session', null, [
            'username' => (string) ($_SESSION['admin_username'] ?? ''),
        ]);
        enma_set_owner_tracking_cookie(false);
        $_SESSION = [];
        session_destroy();
        header('Location: ' . url('/enma/'));
        exit;
    }
}

// Login handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    if ($isLocked) {
        $errors[] = 'Too many login attempts. Try again in a few minutes.';
    }

    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');

    // Authenticate against database users table
    if ($errors === [] && $user !== '' && $pass !== '') {
        try {
            $stmt = $pdo->prepare('SELECT id, username, password_hash, role, status FROM users WHERE username = :username OR email = :email LIMIT 1');
            $stmt->execute([':username' => $user, ':email' => $user]);
            $row = $stmt->fetch();
            
            if ($row && password_verify($pass, $row['password_hash'])) {
                if ($row['status'] !== 'active') {
                    $errors[] = 'Your account is not active.';
                } elseif ($row['role'] !== 'admin') {
                    $errors[] = 'Access denied. Admin privileges required.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['admin_ok'] = true;
                    $_SESSION['admin_user_id'] = (int) $row['id'];
                    $_SESSION['admin_username'] = $row['username'];
                    $_SESSION['admin_role'] = $row['role'];
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_locked_until'] = 0;
                    enma_set_owner_tracking_cookie(true);
                    
                    // Update last login time
                    $updateStmt = $pdo->prepare('UPDATE users SET last_login_at = :now WHERE id = :id');
                    $updateStmt->execute([':now' => now_iso(), ':id' => $row['id']]);
                    enma_record_activity($pdo, 'auth.login', 'user', (int) $row['id'], [
                        'username' => (string) $row['username'],
                    ]);
                    
                    header('Location: ' . url('/enma/'));
                    exit;
                }
            } else {
                $errors[] = 'Invalid credentials.';
            }
        } catch (Throwable $e) {
            error_log('[ENMA auth] ' . $e->getMessage());
            $errors[] = 'Login failed. Verify that the schema is installed and try again.';
        }
    }

    if ($errors === [] && !isset($_SESSION['admin_ok'])) {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= $maxLoginAttempts) {
            $_SESSION['login_locked_until'] = time() + $lockSeconds;
            $_SESSION['login_attempts'] = 0;
            $errors[] = 'Too many login attempts. Try again in 10 minutes.';
        }
    }
}
