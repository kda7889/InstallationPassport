<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/pdf.php';
require_auth();
$user = current_user();
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT i.*, w.name as work_type_name FROM installations i JOIN work_types w ON w.id=i.work_type_id WHERE i.id=:id');
$stmt->execute(['id' => $id]);
$installation = $stmt->fetch();
if (!$installation || !can_access_installation($user, $installation)) {
    http_response_code(403);
    exit('Forbidden');
}

$itemsStmt = db()->prepare('SELECT * FROM installation_items WHERE installation_id=:id ORDER BY sort_order,id');
$itemsStmt->execute(['id' => $id]);
$items = $itemsStmt->fetchAll();

$commonStmt = db()->prepare("SELECT * FROM installation_photos WHERE installation_id=:id AND scope='common' ORDER BY uploaded_at");
$commonStmt->execute(['id' => $id]);
$commonPhotos = $commonStmt->fetchAll();

$itemPhotosMap = array_fill_keys(array_map('intval', array_column($items, 'id')), []);
if ($items) {
    $itemIds = array_map('intval', array_column($items, 'id'));
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $ps = db()->prepare("SELECT * FROM installation_photos WHERE installation_item_id IN ($placeholders) ORDER BY uploaded_at");
    $ps->execute($itemIds);
    foreach ($ps->fetchAll() as $row) {
        $itemPhotosMap[(int) $row['installation_item_id']][] = $row;
    }
}

$missingImportant = [];
$tplStmt = db()->prepare("SELECT code, title FROM photo_templates WHERE work_type_id=:wt AND scope='item' AND is_important=1 AND is_active=1 ORDER BY sort_order");
$tplStmt->execute(['wt' => $installation['work_type_id']]);
$importantTemplates = $tplStmt->fetchAll();
foreach ($items as $item) {
    $codes = array_column($itemPhotosMap[(int) $item['id']] ?? [], 'photo_code');
    foreach ($importantTemplates as $tpl) {
        if (!in_array($tpl['code'], $codes, true)) {
            $missingImportant[] = $item['title'] . ': ' . $tpl['title'];
        }
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$verifyBaseUrl = $host !== '' ? $scheme . '://' . $host : null;

$html = render_installation_pdf_html($installation, $items, $commonPhotos, $itemPhotosMap, $verifyBaseUrl);
$paths = ensure_installation_dirs((string) $installation['number']);
$pdfFile = $paths['base'] . '/documents/warranty_' . $installation['number'] . '_' . date('Ymd_His') . '.pdf';

if (!class_exists('Mpdf\\Mpdf')) {
    http_response_code(500);
    exit('mPDF не установлен. Выполните composer require mpdf/mpdf');
}

$tmpDir = dirname(__DIR__) . '/storage/tmp';
if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
    http_response_code(500);
    exit('Не удалось создать ' . h($tmpDir));
}

$documentsDir = dirname($pdfFile);
if (!is_dir($documentsDir) && !@mkdir($documentsDir, 0775, true) && !is_dir($documentsDir)) {
    http_response_code(500);
    exit('Не удалось создать ' . h($documentsDir));
}

$mpdf = new Mpdf\Mpdf(['tempDir' => $tmpDir]);
$mpdf->WriteHTML($html);
$mpdf->Output($pdfFile, \Mpdf\Output\Destination::FILE);

$rel = str_replace(dirname(__DIR__) . '/', '', $pdfFile);
db()->prepare('UPDATE installations SET pdf_path=:pdf_path, status=:status, updated_at=:updated_at WHERE id=:id')->execute([
    'pdf_path' => $rel,
    'status' => 'pdf_generated',
    'updated_at' => now(),
    'id' => $id,
]);

db()->prepare('INSERT INTO generated_documents (installation_id, document_type, file_path, version, created_by, created_at) VALUES (:installation_id,:document_type,:file_path,:version,:created_by,:created_at)')->execute([
    'installation_id' => $id,
    'document_type' => 'warranty_pdf',
    'file_path' => $rel,
    'version' => 1,
    'created_by' => $user['id'],
    'created_at' => now(),
]);

audit_log('pdf.generated', 'installation', $id, ['number' => $installation['number'], 'missing' => $missingImportant]);

redirect('/download_pdf.php?id=' . $id);
