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
    $customer = (string) post('customer_name', '');
    $customerPhone = (string) post('customer_phone', '');
    $desc = (string) post('work_description', '');
    $status = (string) post('status', 'draft');
    $upd = db()->prepare('UPDATE installations SET customer_name=:customer_name, customer_phone=:customer_phone, work_description=:work_description, status=:status, updated_at=:updated_at WHERE id=:id');
    $upd->execute([
        'customer_name' => $customer,
        'customer_phone' => $customerPhone,
        'work_description' => $desc,
        'status' => $status,
        'updated_at' => now(),
        'id' => $id,
    ]);
    redirect('/installation_edit.php?id=' . $id);
}

$itemStmt = db()->prepare('SELECT * FROM installation_items WHERE installation_id = :id ORDER BY sort_order, id');
$itemStmt->execute(['id' => $id]);
$items = $itemStmt->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Монтаж</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body><div class="container py-3">
<a href="/dashboard.php" class="btn btn-link px-0">← К списку</a>
<h1 class="h4"><?= h((string) $installation['number']) ?></h1>
<div class="mb-2"><a class="btn btn-sm btn-outline-primary" href="/generate_pdf.php?id=<?= (int) $installation['id'] ?>">Сформировать PDF</a> <?php if (!empty($installation['pdf_path'])): ?><a class="btn btn-sm btn-outline-success" href="/download_pdf.php?id=<?= (int) $installation['id'] ?>">Скачать PDF</a><?php endif; ?></div>
<p class="text-muted"><?= h((string) $installation['work_type_name']) ?> · <?= h((string) $installation['address']) ?></p>
<form method="post" class="card card-body mb-3">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
<label class="form-label">Заказчик</label><input class="form-control mb-2" name="customer_name" value="<?= h((string) ($installation['customer_name'] ?? '')) ?>">
<label class="form-label">Телефон заказчика</label><input class="form-control mb-2" name="customer_phone" value="<?= h((string) ($installation['customer_phone'] ?? '')) ?>">
<label class="form-label">Описание работ</label><textarea class="form-control mb-2" rows="3" name="work_description"><?= h((string) ($installation['work_description'] ?? '')) ?></textarea>
<label class="form-label">Статус</label>
<select name="status" class="form-select mb-3">
<?php foreach (['draft','in_progress','photos_partial','ready','pdf_generated','closed'] as $st): ?>
<option value="<?= $st ?>" <?= (($installation['status'] ?? '') === $st ? 'selected' : '') ?>><?= $st ?></option>
<?php endforeach; ?>
</select>
<button class="btn btn-primary">Сохранить</button>
</form>

<div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Элементы монтажа</h2><a class="btn btn-sm btn-primary" href="/installation_item_edit.php?installation_id=<?= (int) $installation['id'] ?>">+ Добавить</a></div>
<div class="list-group mb-3"><?php foreach ($items as $item): ?><a class="list-group-item list-group-item-action" href="/installation_item_edit.php?id=<?= (int) $item['id'] ?>"><?= h((string) $item['title']) ?> <span class="text-muted">· <?= h((string) $item['location']) ?></span></a><?php endforeach; ?><?php if (!$items): ?><div class="list-group-item text-muted">Элементов пока нет.</div><?php endif; ?></div>
</div></body></html>
