<?php

declare(strict_types=1);

function require_auth(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['user'])) {
        redirect('/login.php');
    }

    $user = $_SESSION['user'];
    if (empty($user['is_superadmin']) && (int) ($user['company_id'] ?? 0) > 0) {
        $stmt = db()->prepare('SELECT is_active FROM companies WHERE id = :id');
        $stmt->execute(['id' => (int) $user['company_id']]);
        $active = $stmt->fetchColumn();
        if ($active === false || (int) $active !== 1) {
            $_SESSION = [];
            session_destroy();
            redirect('/login.php?suspended=1');
        }
    }
}

function current_user(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT u.*, COALESCE(c.is_active, 1) AS company_active FROM users u LEFT JOIN companies c ON c.id = u.company_id WHERE u.email = :email AND u.is_active = 1 LIMIT 1');
    $stmt->execute(['email' => mb_strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    if (empty($user['is_superadmin']) && (int) ($user['company_active'] ?? 1) !== 1) {
        return false;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
        'company_id' => (int) ($user['company_id'] ?? 0),
        'is_superadmin' => (int) ($user['is_superadmin'] ?? 0),
    ];

    return true;
}

function logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];
    session_destroy();
}
