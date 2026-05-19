<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$number = trim((string) ($_GET['n'] ?? ''));
$code = trim((string) ($_GET['c'] ?? ''));
$photoId = (int) ($_GET['p'] ?? 0);
$wantFull = ($_GET['full'] ?? '') === '1';

if ($number === '' || $code === '' || $photoId <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$stmt = db()->prepare('SELECT p.file_path, p.thumb_path FROM installation_photos p JOIN installations i ON i.id = p.installation_id WHERE p.id = :photo_id AND i.number = :number AND i.verification_code = :code LIMIT 1');
$stmt->execute(['photo_id' => $photoId, 'number' => $number, 'code' => $code]);
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    exit('Not found');
}

$path = dirname(__DIR__) . '/' . ($wantFull ? $photo['file_path'] : $photo['thumb_path']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
readfile($path);
