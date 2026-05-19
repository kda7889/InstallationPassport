<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$logoPath = setting('company_logo_path');
if ($logoPath === '') {
    http_response_code(404);
    exit;
}

$abs = dirname(__DIR__) . '/' . ltrim($logoPath, '/');
if (!is_file($abs)) {
    http_response_code(404);
    exit;
}

$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($abs);
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=3600');
readfile($abs);
