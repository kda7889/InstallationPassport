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

@unlink(dirname(__DIR__) . '/' . $photo['file_path']);
@unlink(dirname(__DIR__) . '/' . $photo['thumb_path']);
$dbs = db()->prepare('DELETE FROM installation_photos WHERE id = :id');
$dbs->execute(['id' => $id]);

$itemId = (int) ($photo['installation_item_id'] ?? 0);
redirect('/installation_item_edit.php?id=' . $itemId);
