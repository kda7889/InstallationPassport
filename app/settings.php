<?php

declare(strict_types=1);

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM app_settings')->fetchAll() as $row) {
            $cache[(string) $row['key']] = (string) ($row['value'] ?? '');
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO app_settings (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute(['key' => $key, 'value' => $value]);
}

function company(int $companyId): ?array
{
    static $cache = [];
    if (isset($cache[$companyId])) {
        return $cache[$companyId];
    }
    $stmt = db()->prepare('SELECT * FROM companies WHERE id = :id');
    $stmt->execute(['id' => $companyId]);
    $row = $stmt->fetch();
    $cache[$companyId] = $row ?: null;
    return $cache[$companyId];
}

function company_branding(?int $companyId = null): array
{
    $blank = ['id' => 0, 'name' => '', 'inn' => '', 'phone' => '', 'email' => '', 'address' => '', 'logo_path' => ''];
    if ($companyId === null) {
        $user = current_user();
        $companyId = isset($user['company_id']) ? (int) $user['company_id'] : 0;
    }
    if ($companyId <= 0) {
        return $blank;
    }
    $row = company($companyId);
    if (!$row) {
        return $blank;
    }
    return [
        'id' => (int) $row['id'],
        'name' => (string) ($row['name'] ?? ''),
        'inn' => (string) ($row['inn'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'address' => (string) ($row['address'] ?? ''),
        'logo_path' => (string) ($row['logo_path'] ?? ''),
    ];
}

function update_company(int $companyId, array $fields): void
{
    $allowed = ['name', 'inn', 'phone', 'email', 'address', 'logo_path'];
    $set = [];
    $params = ['id' => $companyId];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $fields)) {
            $set[] = "$field = :$field";
            $params[$field] = $fields[$field];
        }
    }
    if (!$set) {
        return;
    }
    db()->prepare('UPDATE companies SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
}

function review_criteria(): array
{
    return [
        'punctuality' => 'Пунктуальность',
        'quality' => 'Качество монтажа',
        'cleanliness' => 'Чистота',
        'communication' => 'Общение',
        'price_transparency' => 'Прозрачное ценообразование',
    ];
}
