<?php

declare(strict_types=1);

function can_access_installation(array $user, array $installation): bool
{
    if (($user['role'] ?? null) === 'admin') {
        return true;
    }

    return (int) ($installation['user_id'] ?? 0) === (int) ($user['id'] ?? -1);
}
