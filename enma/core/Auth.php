<?php
namespace Enma\Core;

class Auth {
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Soporte para sesión legacy (admin_ok) y nueva (admin_user_id)
        return isset($_SESSION['admin_ok']) || (isset($_SESSION['admin_user_id']) && isset($_SESSION['admin_username']));
    }

    public static function user() {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['admin_user_id'] ?? null,
            'username' => $_SESSION['admin_username'] ?? 'Admin',
            'role' => $_SESSION['admin_role'] ?? 'admin'
        ];
    }

    public static function login($username, $password) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $db = Database::getInstance()->getConnection();
        
        // Buscar por username O email
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1");
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Verificar rol y estado
            if ($user['role'] !== 'admin' || $user['status'] !== 'active') {
                return ['success' => false, 'message' => 'Acceso denegado: permisos insuficientes o cuenta inactiva.'];
            }

            // Actualizar último login
            $update = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
            $update->execute(['id' => $user['id']]);

            // Guardar sesión (compatible con legacy)
            $_SESSION['admin_ok'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];

            return ['success' => true];
        }

        return ['success' => false, 'message' => 'Credenciales inválidas.'];
    }

    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
    }

    public static function isAdmin() {
        $user = self::user();
        return $user && ($user['role'] === 'admin' || isset($_SESSION['admin_ok']));
    }
}
