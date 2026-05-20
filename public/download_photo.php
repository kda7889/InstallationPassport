<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT p.*, i.user_id FROM installation_photos p JOIN installations i ON i.id = p.installation_id WHERE p.id=:id');
$stmt->execute(['id' => $id]);
$photo = $stmt->fetch();
if (!$photo || (($user['role'] ?? '') !== 'admin' && (int) $photo['user_id'] !== (int) $user['id'])) {
    http_response_code(403);
    exit('Forbidden');
}
$wantFull = ($_GET['size'] ?? '') === 'full';
$relPath = $wantFull ? ($photo['file_path'] ?? '') : ($photo['thumb_path'] ?? '');
$path = dirname(__DIR__) . '/' . $relPath;
if (!$relPath || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
}
header('Content-Type: image/jpeg');
readfile($path);
