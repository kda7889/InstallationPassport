<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, user_id, number, pdf_path FROM installations WHERE id=:id');
$stmt->execute(['id' => $id]);
$installation = $stmt->fetch();
if (!$installation || (($user['role'] ?? '') !== 'admin' && (int) $installation['user_id'] !== (int) $user['id'])) {
    http_response_code(403);
    exit('Forbidden');
}
if (empty($installation['pdf_path'])) {
    exit('PDF еще не сформирован');
}
$path = dirname(__DIR__) . '/' . $installation['pdf_path'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Файл не найден');
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="warranty_' . $installation['number'] . '.pdf"');
readfile($path);
