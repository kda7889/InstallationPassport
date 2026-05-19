<?php

declare(strict_types=1);

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $metadata = null): void
{
    $user = current_user();
    $stmt = db()->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, metadata, ip, created_at) VALUES (:user_id, :action, :entity_type, :entity_id, :metadata, :ip, :created_at)');
    $stmt->execute([
        'user_id' => $user['id'] ?? null,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        'ip' => client_ip(),
        'created_at' => now(),
    ]);
}

function login_rate_limit_block(string $ip): bool
{
    if ($ip === '') {
        return false;
    }
    $hourAgo = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
    $fiveMinAgo = (new DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s');

    db()->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff')
        ->execute(['cutoff' => $hourAgo]);

    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND success = 0 AND attempted_at > :cutoff');
    $stmt->execute(['ip' => $ip, 'cutoff' => $fiveMinAgo]);
    return ((int) $stmt->fetchColumn()) >= 10;
}

function record_login_attempt(string $ip, string $email, bool $success): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (ip, email, success, attempted_at) VALUES (:ip, :email, :success, :attempted_at)');
    $stmt->execute([
        'ip' => $ip,
        'email' => $email,
        'success' => $success ? 1 : 0,
        'attempted_at' => now(),
    ]);
}
