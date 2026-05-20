<?php

declare(strict_types=1);

function mask_name(string $fullName): string
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return '';
    }
    $parts = preg_split('/\s+/', $fullName) ?: [];
    if (count($parts) === 1) {
        $first = mb_substr($parts[0], 0, 1);
        return $first . '…';
    }
    $first = $parts[0];
    $rest = '';
    foreach (array_slice($parts, 1) as $p) {
        if ($p !== '') {
            $rest .= ' ' . mb_strtoupper(mb_substr($p, 0, 1)) . '.';
        }
    }
    return $first . $rest;
}

function mask_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if ($digits === '') {
        return '';
    }
    $len = strlen($digits);
    if ($len < 4) {
        return str_repeat('•', $len);
    }
    $tail = substr($digits, -2);
    $head = $len >= 11 ? '+' . $digits[0] : '';
    return trim($head . ' ••• ••• ••' . $tail);
}

function mask_address(string $address): string
{
    $address = trim($address);
    if ($address === '') {
        return '';
    }
    $masked = preg_replace_callback(
        '/(кв\.?\s*|квартира\s*|оф\.?\s*|офис\s*|пом\.?\s*)(\d+\w*)/iu',
        static fn(array $m): string => $m[1] . '••',
        $address
    );
    return (string) $masked;
}

function mask_email(string $email): string
{
    $email = trim($email);
    $at = strpos($email, '@');
    if ($at === false || $at < 1) {
        return '';
    }
    $local = substr($email, 0, $at);
    $domain = substr($email, $at);
    $first = mb_substr($local, 0, 1);
    return $first . '•••' . $domain;
}
