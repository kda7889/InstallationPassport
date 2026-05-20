<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$number = mb_strtoupper(trim((string) ($_GET['n'] ?? '')));
$code = strtolower(trim((string) ($_GET['c'] ?? '')));
$photoId = (int) ($_GET['p'] ?? 0);
$wantFull = ($_GET['full'] ?? '') === '1';

if ($number === '' || $code === '' || $photoId <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$stmt = db()->prepare('SELECT p.file_path, p.thumb_path, i.verification_code, i.access_token FROM installation_photos p JOIN installations i ON i.id = p.installation_id WHERE p.id = :photo_id AND i.number = :number LIMIT 1');
$stmt->execute(['photo_id' => $photoId, 'number' => $number]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Not found');
}

$valid = (is_string($row['verification_code']) && hash_equals((string) $row['verification_code'], $code))
    || (is_string($row['access_token']) && hash_equals((string) $row['access_token'], $code));
if (!$valid) {
    http_response_code(404);
    exit('Not found');
}

$path = dirname(__DIR__) . '/' . ($wantFull ? $row['file_path'] : $row['thumb_path']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
readfile($path);
