<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!csrf_validate(post('_csrf'))) {
    http_response_code(400);
    exit('CSRF error');
}

$itemId = (int) post('id', '0');

$stmt = db()->prepare('SELECT it.installation_id, i.user_id FROM installation_items it JOIN installations i ON i.id = it.installation_id WHERE it.id = :id');
$stmt->execute(['id' => $itemId]);
$row = $stmt->fetch();
if (!$row || (($user['role'] ?? '') !== 'admin' && (int) $row['user_id'] !== (int) $user['id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$root = dirname(__DIR__);
$photosStmt = db()->prepare('SELECT file_path, thumb_path FROM installation_photos WHERE installation_item_id = :item_id');
$photosStmt->execute(['item_id' => $itemId]);
foreach ($photosStmt->fetchAll() as $photo) {
    @unlink($root . '/' . $photo['file_path']);
    @unlink($root . '/' . $photo['thumb_path']);
}

db()->prepare('DELETE FROM installation_items WHERE id = :id')->execute(['id' => $itemId]);

audit_log('item.deleted', 'installation_item', $itemId, ['installation_id' => (int) $row['installation_id']]);

redirect('/installation_edit.php?id=' . (int) $row['installation_id']);
