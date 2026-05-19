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

$installationId = (int) post('installation_id', '0');
$itemId = (int) post('installation_item_id', '0');
$scope = post('scope', 'item') === 'common' ? 'common' : 'item';
$photoCode = (string) post('photo_code', 'other');
$title = trim((string) post('title', ''));
if ($title === '') {
    $title = 'Фото';
}

$iStmt = db()->prepare('SELECT * FROM installations WHERE id = :id');
$iStmt->execute(['id' => $installationId]);
$installation = $iStmt->fetch();
if (!$installation || !can_access_installation($user, $installation)) {
    http_response_code(403);
    exit('Forbidden');
}

$itemNumber = 'common';
if ($scope === 'item') {
    $itemStmt = db()->prepare('SELECT id, item_number FROM installation_items WHERE id=:id AND installation_id=:installation_id');
    $itemStmt->execute(['id' => $itemId, 'installation_id' => $installationId]);
    $item = $itemStmt->fetch();
    if (!$item) {
        http_response_code(400);
        exit('Item not found');
    }
    $itemNumber = (string) $item['item_number'];
}

if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
    http_response_code(400);
    exit('No file');
}

$file = $_FILES['photo'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('Upload error');
}

if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
    http_response_code(400);
    exit('Invalid upload');
}

$config = require __DIR__ . '/../app/config.php';
if ((int) $file['size'] > (int) $config['max_upload_bytes']) {
    http_response_code(400);
    exit('File too large');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file((string) $file['tmp_name']);
$ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
$tmpHeicConverted = null;

register_shutdown_function(static function () use (&$tmpHeicConverted) {
    if ($tmpHeicConverted !== null && is_file($tmpHeicConverted)) {
        @unlink($tmpHeicConverted);
    }
});

if ($mime === 'image/heic' || $mime === 'image/heif' || in_array($ext, ['heic', 'heif'], true)) {
    if (!extension_loaded('imagick')) {
        http_response_code(400);
        exit('Формат HEIC не поддерживается на этом сервере. Загрузите JPG или включите на iPhone «Настройки → Камера → Форматы → Наиболее совместимые».');
    }
    try {
        $im = new Imagick((string) $file['tmp_name']);
        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality(95);
        $tmpHeicConverted = (string) $file['tmp_name'] . '.jpg';
        $im->writeImage($tmpHeicConverted);
        $im->clear();
        $im->destroy();
    } catch (Throwable $e) {
        http_response_code(400);
        exit('Не удалось прочитать HEIC.');
    }
    $file['tmp_name'] = $tmpHeicConverted;
    $mime = 'image/jpeg';
}

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    exit('Неподдерживаемый формат изображения.');
}

$source = image_load_by_mime((string) $file['tmp_name'], $mime);
if (!$source) {
    http_response_code(400);
    exit('Cannot read image');
}

$source = image_fix_orientation((string) $file['tmp_name'], $source);
$full = image_resize($source, (int) $config['image_max_side']);
$thumb = image_resize($source, (int) $config['thumb_max_side']);

$installationNumber = (string) $installation['number'];
$dt = date('Ymd_His');
$rand = bin2hex(random_bytes(2));
$fileName = sprintf('%s_%s_%s_%s_%s.jpg', $installationNumber, $itemNumber, preg_replace('/[^a-z0-9_\-]/i', '_', $photoCode), $dt, $rand);

$paths = ensure_installation_dirs($installationNumber);
$base = $paths['base'];
if ($scope === 'common') {
    $compressedDir = "$base/common/photos/compressed";
    $thumbDir = "$base/common/photos/thumbnails";
} else {
    $itemDir = "$base/items/$itemNumber/photos";
    $compressedDir = "$itemDir/compressed";
    $thumbDir = "$itemDir/thumbnails";
    if (!is_dir($compressedDir)) {
        mkdir($compressedDir, 0775, true);
    }
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0775, true);
    }
}

$fullPath = "$compressedDir/$fileName";
$thumbPath = "$thumbDir/$fileName";
$quality = (int) $config['jpeg_quality'];
if (!@imagejpeg($full, $fullPath, $quality) || !@imagejpeg($thumb, $thumbPath, $quality)) {
    @unlink($fullPath);
    @unlink($thumbPath);
    imagedestroy($source);
    imagedestroy($full);
    imagedestroy($thumb);
    http_response_code(500);
    exit('Не удалось сохранить файл на диск. Проверьте права на storage/.');
}

$width = imagesx($full);
$height = imagesy($full);
$fileSize = (int) @filesize($fullPath);

imagedestroy($source);
imagedestroy($full);
imagedestroy($thumb);

$stmt = db()->prepare('INSERT INTO installation_photos (installation_id, installation_item_id, scope, photo_code, title, file_path, thumb_path, mime_type, file_size, width, height, uploaded_by, uploaded_at) VALUES (:installation_id,:installation_item_id,:scope,:photo_code,:title,:file_path,:thumb_path,:mime_type,:file_size,:width,:height,:uploaded_by,:uploaded_at)');
$stmt->execute([
    'installation_id' => $installationId,
    'installation_item_id' => $scope === 'item' ? $itemId : null,
    'scope' => $scope,
    'photo_code' => $photoCode,
    'title' => $title,
    'file_path' => str_replace(dirname(__DIR__) . '/', '', $fullPath),
    'thumb_path' => str_replace(dirname(__DIR__) . '/', '', $thumbPath),
    'mime_type' => 'image/jpeg',
    'file_size' => $fileSize,
    'width' => $width,
    'height' => $height,
    'uploaded_by' => $user['id'],
    'uploaded_at' => now(),
]);
$photoId = (int) db()->lastInsertId();

audit_log('photo.uploaded', 'photo', $photoId, [
    'installation_id' => $installationId,
    'installation_item_id' => $scope === 'item' ? $itemId : null,
    'scope' => $scope,
    'photo_code' => $photoCode,
]);

if ($scope === 'common') {
    redirect('/installation_edit.php?id=' . $installationId);
}
redirect('/installation_item_edit.php?id=' . $itemId);
