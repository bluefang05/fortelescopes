<?php

declare(strict_types=1);

if (!$authenticated) {
    return;
}

$editingUser = null;
$userSearch = trim((string) ($_GET['user_q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $username = trim((string) ($_POST['username'] ?? ''));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = trim((string) ($_POST['role'] ?? 'user'));
    $status = trim((string) ($_POST['status'] ?? 'active'));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!in_array($role, ['admin', 'user'], true)) {
        $errors[] = 'Invalid role.';
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Invalid status.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email OR username = :username');
        $stmt->execute([':email' => $email, ':username' => $username]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'Email or username already exists.';
        }
    }

    if ($errors === []) {
        $now = now_iso();
        $stmt = $pdo->prepare(
            'INSERT INTO users (
                email, username, password_hash, display_name, role, status, created_at, updated_at
            ) VALUES (
                :email, :username, :password_hash, :display_name, :role, :status, :created_at, :updated_at
            )'
        );
        $stmt->execute([
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':display_name' => $displayName !== '' ? $displayName : null,
            ':role' => $role,
            ':status' => $status,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $newUserId = (int) $pdo->lastInsertId();
        enma_record_activity($pdo, 'user.create', 'user', $newUserId, [
            'email' => $email,
            'username' => $username,
            'role' => $role,
            'status' => $status,
        ]);
        $flash = 'User created successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    $id = (int) ($_POST['id'] ?? 0);
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $username = trim((string) ($_POST['username'] ?? ''));
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = trim((string) ($_POST['role'] ?? 'user'));
    $status = trim((string) ($_POST['status'] ?? 'active'));

    if ($id <= 0) {
        $errors[] = 'Invalid user ID.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!in_array($role, ['admin', 'user'], true)) {
        $errors[] = 'Invalid role.';
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Invalid status.';
    }

    $existingUser = null;
    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $existingUser = $stmt->fetch();
        if (!$existingUser) {
            $errors[] = 'User not found.';
        }
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (email = :email OR username = :username) AND id <> :id');
        $stmt->execute([':email' => $email, ':username' => $username, ':id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'Email or username already exists.';
        }
    }

    $isCurrentAdmin = isset($_SESSION['admin_user_id']) && (int) $_SESSION['admin_user_id'] === $id;
    if ($errors === [] && $existingUser) {
        $wasActiveAdmin = ($existingUser['role'] ?? '') === 'admin' && ($existingUser['status'] ?? '') === 'active';
        $willRemainActiveAdmin = $role === 'admin' && $status === 'active';
        if ($wasActiveAdmin && !$willRemainActiveAdmin && enma_count_active_admins($pdo, $id) === 0) {
            $errors[] = 'You cannot remove or deactivate the last active admin.';
        }
        if ($isCurrentAdmin && $status !== 'active') {
            $errors[] = 'You cannot deactivate your current admin account.';
        }
    }

    if ($errors === [] && $existingUser) {
        $now = now_iso();
        $sql = 'UPDATE users SET
                    email = :email,
                    username = :username,
                    display_name = :display_name,
                    role = :role,
                    status = :status,
                    updated_at = :updated_at';
        $params = [
            ':email' => $email,
            ':username' => $username,
            ':display_name' => $displayName !== '' ? $displayName : null,
            ':role' => $role,
            ':status' => $status,
            ':updated_at' => $now,
            ':id' => $id,
        ];
        if ($password !== '') {
            $sql .= ', password_hash = :password_hash';
            $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($isCurrentAdmin) {
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_role'] = $role;
        }

        enma_record_activity($pdo, 'user.update', 'user', $id, [
            'email' => $email,
            'username' => $username,
            'role' => $role,
            'status' => $status,
            'password_changed' => $password !== '',
        ]);
        $editingUser = null;
        $flash = 'User updated successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $userRow = $stmt->fetch();
            if (!$userRow) {
                $errors[] = 'User not found.';
            } elseif (isset($_SESSION['admin_user_id']) && (int) $_SESSION['admin_user_id'] === $id) {
                $errors[] = 'You cannot delete your current admin account.';
            } elseif (($userRow['role'] ?? '') === 'admin' && ($userRow['status'] ?? '') === 'active' && enma_count_active_admins($pdo, $id) === 0) {
                $errors[] = 'You cannot delete the last active admin.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute([':id' => $id]);
                enma_record_activity($pdo, 'user.delete', 'user', $id, [
                    'email' => (string) ($userRow['email'] ?? ''),
                    'username' => (string) ($userRow['username'] ?? ''),
                ]);
                $flash = 'User deleted successfully.';
            }
        }
    }
}

if (($_GET['edit_user'] ?? '') !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_GET['edit_user']]);
    $editingUser = $stmt->fetch() ?: null;
}
