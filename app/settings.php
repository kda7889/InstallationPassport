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

function company_branding(): array
{
    return [
        'name' => setting('company_name'),
        'inn' => setting('company_inn'),
        'phone' => setting('company_phone'),
        'address' => setting('company_address'),
        'email' => setting('company_email'),
        'logo_path' => setting('company_logo_path'),
    ];
}
