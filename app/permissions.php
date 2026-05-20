<?php

declare(strict_types=1);

function is_superadmin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return !empty($user['is_superadmin']);
}

function is_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    return ($user['role'] ?? null) === 'admin' || !empty($user['is_superadmin']);
}

function require_admin(): void
{
    require_auth();
    if (!is_admin()) {
        http_response_code(403);
        exit('Доступ запрещён');
    }
}

function require_superadmin(): void
{
    require_auth();
    if (!is_superadmin()) {
        http_response_code(403);
        exit('Доступ запрещён');
    }
}

function can_access_installation(array $user, array $installation): bool
{
    if (!empty($user['is_superadmin'])) {
        return true;
    }
    if ((int) ($user['company_id'] ?? 0) !== (int) ($installation['company_id'] ?? 0)) {
        return false;
    }
    if (($user['role'] ?? null) === 'admin') {
        return true;
    }
    return (int) ($installation['user_id'] ?? 0) === (int) ($user['id'] ?? -1);
}
