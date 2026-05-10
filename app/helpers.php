<?php

declare(strict_types=1);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function now(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function post(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $default;

    if (!is_string($value)) {
        return $default;
    }

    return trim($value);
}
