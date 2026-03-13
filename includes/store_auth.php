<?php

declare(strict_types=1);

require_once __DIR__ . '/store_bootstrap.php';

function storeRegisterUser(array $input): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $fullName = trim((string) ($input['full_name'] ?? $input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = trim((string) ($input['phone'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $confirmPassword = (string) ($input['confirm_password'] ?? '');

    if ($fullName === '' || $email === '' || $password === '') {
        return ['success' => false, 'message' => 'Name, email, and password are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }
    if ($confirmPassword !== '' && $password !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare('INSERT INTO users (full_name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, 1)');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Could not create account right now.'];
    }

    $stmt->bind_param('ssss', $fullName, $email, $phone, $passwordHash);
    $ok = $stmt->execute();
    $errorNo = $stmt->errno;
    $userId = (int) $stmt->insert_id;
    $stmt->close();

    if (!$ok) {
        if ($errorNo === 1062) {
            return ['success' => false, 'message' => 'Email is already registered.'];
        }
        return ['success' => false, 'message' => 'Could not create account right now.'];
    }

    storeEnsureSession();
    $_SESSION['user_id'] = $userId;

    return ['success' => true, 'message' => 'Account created successfully.'];
}

function storeLoginUser(string $emailInput, string $password): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $email = strtolower(trim($emailInput));
    if ($email === '' || $password === '') {
        return ['success' => false, 'message' => 'Email and password are required.'];
    }

    $stmt = $db->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to login right now.'];
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }

    storeEnsureSession();
    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);

    return ['success' => true, 'message' => 'Logged in successfully.'];
}

function storeLogoutUser(): void
{
    storeEnsureSession();
    unset($_SESSION['user_id']);
}

function storeCurrentUser(): ?array
{
    storeEnsureSession();
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return null;
    }

    $stmt = $db->prepare('SELECT id, full_name, email, phone, is_active, created_at FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'full_name' => (string) ($row['full_name'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function storeCurrentUserId(): ?int
{
    $user = storeCurrentUser();
    return $user ? (int) $user['id'] : null;
}

function storeRequireUser(string $next = ''): void
{
    if (storeCurrentUserId() !== null) {
        return;
    }

    $target = 'login.php';
    if ($next !== '') {
        $target .= '?next=' . rawurlencode($next);
    }

    header('Location: ' . $target);
    exit;
}

function storeLoginAdmin(string $emailInput, string $password): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $email = strtolower(trim($emailInput));
    if ($email === '' || $password === '') {
        return ['success' => false, 'message' => 'Email and password are required.'];
    }

    $stmt = $db->prepare('SELECT id, password_hash, is_active FROM admin_users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to login right now.'];
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $admin = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$admin || (int) ($admin['is_active'] ?? 0) !== 1) {
        return ['success' => false, 'message' => 'Invalid admin credentials.'];
    }
    if (!password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
        return ['success' => false, 'message' => 'Invalid admin credentials.'];
    }

    $adminId = (int) ($admin['id'] ?? 0);
    storeEnsureSession();
    $_SESSION['admin_id'] = $adminId;
    $db->query('UPDATE admin_users SET last_login_at = NOW() WHERE id = ' . $adminId);

    return ['success' => true, 'message' => 'Admin login successful.'];
}

function storeLogoutAdmin(): void
{
    storeEnsureSession();
    unset($_SESSION['admin_id']);
}

function storeCurrentAdmin(): ?array
{
    storeEnsureSession();
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        return null;
    }

    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return null;
    }

    $stmt = $db->prepare('SELECT id, full_name, email, is_active FROM admin_users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'full_name' => (string) ($row['full_name'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
    ];
}

function storeCurrentAdminId(): ?int
{
    $admin = storeCurrentAdmin();
    return $admin ? (int) $admin['id'] : null;
}

function storeRequireAdmin(): void
{
    if (storeCurrentAdminId() !== null) {
        return;
    }

    header('Location: dashboard.php?admin_login=1');
    exit;
}
