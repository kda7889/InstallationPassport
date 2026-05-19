<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT i.*, w.name as work_type_name FROM installations i JOIN work_types w ON w.id = i.work_type_id WHERE i.id = :id');
$stmt->execute(['id' => $id]);
$installation = $stmt->fetch();
if (!$installation || !can_access_installation($user, $installation)) {
    http_response_code(403);
    exit('Доступ запрещен');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate(post('_csrf'))) {
    $upd = db()->prepare('UPDATE installations SET customer_name=:customer_name, customer_phone=:customer_phone, installer_name=:installer_name, installer_phone=:installer_phone, company_name=:company_name, company_inn=:company_inn, warranty_months=:warranty_months, work_description=:work_description, comment=:comment, status=:status, updated_at=:updated_at WHERE id=:id');
    $upd->execute([
        'customer_name' => (string) post('customer_name', ''),
        'customer_phone' => (string) post('customer_phone', ''),
        'installer_name' => (string) post('installer_name', ''),
        'installer_phone' => (string) post('installer_phone', ''),
        'company_name' => (string) post('company_name', ''),
        'company_inn' => (string) post('company_inn', ''),
        'warranty_months' => (int) post('warranty_months', '12'),
        'work_description' => (string) post('work_description', ''),
        'comment' => (string) post('comment', ''),
        'status' => (string) post('status', 'draft'),
        'updated_at' => now(),
        'id' => $id,
    ]);
    redirect('/installation_edit.php?id=' . $id);
}

$itemStmt = db()->prepare('SELECT * FROM installation_items WHERE installation_id = :id ORDER BY sort_order, id');
$itemStmt->execute(['id' => $id]);
$items = $itemStmt->fetchAll();

$commonPhotosStmt = db()->prepare("SELECT * FROM installation_photos WHERE installation_id=:id AND scope='common' ORDER BY uploaded_at DESC");
$commonPhotosStmt->execute(['id' => $id]);
$commonPhotos = $commonPhotosStmt->fetchAll();

$warningStmt = db()->prepare("SELECT code, title FROM photo_templates WHERE work_type_id=:wt AND scope='item' AND is_important=1 AND is_active=1 ORDER BY sort_order");
$warningStmt->execute(['wt' => $installation['work_type_id']]);
$importantTemplates = $warningStmt->fetchAll();
$missingImportant = [];
foreach ($items as $item) {
    $codesStmt = db()->prepare('SELECT photo_code FROM installation_photos WHERE installation_item_id=:item_id');
    $codesStmt->execute(['item_id' => $item['id']]);
    $codes = array_column($codesStmt->fetchAll(), 'photo_code');
    foreach ($importantTemplates as $tpl) {
        if (!in_array($tpl['code'], $codes, true)) {
            $missingImportant[] = $item['title'] . ': ' . $tpl['title'];
        }
    }
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Монтаж</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body><div class="container py-3">
<a href="/dashboard.php" class="btn btn-link px-0">← К списку</a>
<h1 class="h4"><?= h((string) $installation['number']) ?></h1>
<div class="mb-2"><a class="btn btn-sm btn-outline-primary" href="/generate_pdf.php?id=<?= (int) $installation['id'] ?>">Сформировать PDF</a> <?php if (!empty($installation['pdf_path'])): ?><a class="btn btn-sm btn-outline-success" href="/download_pdf.php?id=<?= (int) $installation['id'] ?>">Скачать PDF</a><?php endif; ?></div>
<?php if ($missingImportant): ?><div class="alert alert-warning"><div class="fw-semibold mb-1">Не загружены важные фото:</div><ul class="mb-0"><?php foreach ($missingImportant as $m): ?><li><?= h($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<p class="text-muted"><?= h((string) $installation['work_type_name']) ?> · <?= h((string) $installation['address']) ?></p>
<form method="post" class="card card-body mb-3">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
<label class="form-label">Заказчик</label><input class="form-control mb-2" name="customer_name" value="<?= h((string) ($installation['customer_name'] ?? '')) ?>">
<label class="form-label">Телефон заказчика</label><input class="form-control mb-2" name="customer_phone" value="<?= h((string) ($installation['customer_phone'] ?? '')) ?>">
<label class="form-label">Исполнитель</label><input class="form-control mb-2" name="installer_name" value="<?= h((string) ($installation['installer_name'] ?? '')) ?>">
<label class="form-label">Телефон исполнителя</label><input class="form-control mb-2" name="installer_phone" value="<?= h((string) ($installation['installer_phone'] ?? '')) ?>">
<label class="form-label">Организация/ИП</label><input class="form-control mb-2" name="company_name" value="<?= h((string) ($installation['company_name'] ?? '')) ?>">
<label class="form-label">ИНН/ОГРН</label><input class="form-control mb-2" name="company_inn" value="<?= h((string) ($installation['company_inn'] ?? '')) ?>">
<label class="form-label">Гарантия (мес.)</label><input class="form-control mb-2" type="number" min="1" name="warranty_months" value="<?= h((string) ($installation['warranty_months'] ?? 12)) ?>">
<label class="form-label">Описание работ</label><textarea class="form-control mb-2" rows="3" name="work_description"><?= h((string) ($installation['work_description'] ?? '')) ?></textarea>
<label class="form-label">Комментарий</label><textarea class="form-control mb-2" rows="2" name="comment"><?= h((string) ($installation['comment'] ?? '')) ?></textarea>
<label class="form-label">Статус</label>
<select name="status" class="form-select mb-3">
<?php foreach (['draft','in_progress','photos_partial','ready','pdf_generated','closed'] as $st): ?>
<option value="<?= $st ?>" <?= (($installation['status'] ?? '') === $st ? 'selected' : '') ?>><?= $st ?></option>
<?php endforeach; ?>
</select>
<button class="btn btn-primary">Сохранить</button>
</form>

<h2 class="h5">Общие фото объекта</h2>
<form method="post" action="/photo_upload.php" enctype="multipart/form-data" class="card card-body mb-3">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
<input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>">
<input type="hidden" name="scope" value="common">
<div class="mb-2"><label class="form-label">Код фото</label><input name="photo_code" class="form-control" value="before_work"></div>
<div class="mb-2"><label class="form-label">Название</label><input name="title" class="form-control" value="Общее фото"></div>
<div class="mb-3"><input type="file" name="photo" accept="image/*" class="form-control" required></div>
<button class="btn btn-success">Загрузить общее фото</button>
</form>
<div class="row mb-3"><?php foreach ($commonPhotos as $photo): ?><div class="col-6 mb-3"><div class="card"><img src="/download_photo.php?id=<?= (int)$photo['id'] ?>" class="card-img-top" alt="thumb"><div class="card-body p-2"><div class="small"><?= h((string)$photo['title']) ?></div><form method="post" action="/photo_delete.php" class="mt-2"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $photo['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Удалить</button></form></div></div></div><?php endforeach; ?></div>

<div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Элементы монтажа</h2><a class="btn btn-sm btn-primary" href="/installation_item_edit.php?installation_id=<?= (int) $installation['id'] ?>">+ Добавить</a></div>
<div class="list-group mb-3"><?php foreach ($items as $item): ?><a class="list-group-item list-group-item-action" href="/installation_item_edit.php?id=<?= (int) $item['id'] ?>"><?= h((string) $item['title']) ?> <span class="text-muted">· <?= h((string) $item['location']) ?></span></a><?php endforeach; ?><?php if (!$items): ?><div class="list-group-item text-muted">Элементов пока нет.</div><?php endif; ?></div>
</div></body></html>
