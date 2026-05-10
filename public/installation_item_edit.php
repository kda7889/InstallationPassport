<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

$itemId = (int) ($_GET['id'] ?? 0);
$installationId = (int) ($_GET['installation_id'] ?? 0);
$item = null;

if ($itemId > 0) {
    $stmt = db()->prepare('SELECT * FROM installation_items WHERE id = :id');
    $stmt->execute(['id' => $itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        exit('Элемент не найден');
    }
    $installationId = (int) $item['installation_id'];
}

$iStmt = db()->prepare('SELECT * FROM installations WHERE id = :id');
$iStmt->execute(['id' => $installationId]);
$installation = $iStmt->fetch();
if (!$installation || !can_access_installation($user, $installation)) {
    http_response_code(403);
    exit('Доступ запрещен');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate(post('_csrf'))) {
    $title = (string) post('title', '');
    $location = (string) post('location', '');
    $brand = (string) post('brand', '');
    $model = (string) post('model', '');
    $indoor = (string) post('indoor_serial', '');
    $outdoor = (string) post('outdoor_serial', '');
    $extra = json_encode(['indoor_serial' => $indoor, 'outdoor_serial' => $outdoor], JSON_UNESCAPED_UNICODE);

    if ($item) {
        $upd = db()->prepare('UPDATE installation_items SET title=:title, location=:location, brand=:brand, model=:model, extra_data_json=:extra, updated_at=:updated_at WHERE id=:id');
        $upd->execute(['title'=>$title,'location'=>$location,'brand'=>$brand,'model'=>$model,'extra'=>$extra,'updated_at'=>now(),'id'=>$itemId]);
    } else {
        $countStmt = db()->prepare('SELECT COUNT(*) FROM installation_items WHERE installation_id = :installation_id');
        $countStmt->execute(['installation_id' => $installationId]);
        $num = (int) $countStmt->fetchColumn() + 1;
        $itemNumber = sprintf('item-%04d', $num);
        $ins = db()->prepare('INSERT INTO installation_items (installation_id, work_type_id, item_number, title, location, brand, model, extra_data_json, created_at, updated_at) VALUES (:installation_id, :work_type_id, :item_number, :title, :location, :brand, :model, :extra, :created_at, :updated_at)');
        $ins->execute([
            'installation_id'=>$installationId,
            'work_type_id'=>$installation['work_type_id'],
            'item_number'=>$itemNumber,
            'title'=>$title !== '' ? $title : ('Элемент #' . $num),
            'location'=>$location,
            'brand'=>$brand,
            'model'=>$model,
            'extra'=>$extra,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }

    redirect('/installation_edit.php?id=' . $installationId);
}

$extraData = $item ? json_decode((string) ($item['extra_data_json'] ?? '{}'), true) : [];

$photosStmt = db()->prepare('SELECT * FROM installation_photos WHERE installation_item_id = :item_id ORDER BY uploaded_at DESC');
$photosStmt->execute(['item_id' => $itemId]);
$photos = $photosStmt->fetchAll();

?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Элемент</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body><div class="container py-3">
<a href="/installation_edit.php?id=<?= (int) $installationId ?>" class="btn btn-link px-0">← К монтажу</a>
<h1 class="h4"><?= $item ? 'Редактирование элемента' : 'Новый элемент' ?></h1>
<form method="post" class="card card-body">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
<label class="form-label">Название элемента</label><input class="form-control mb-2" name="title" value="<?= h((string) ($item['title'] ?? '')) ?>" required>
<label class="form-label">Место установки</label><input class="form-control mb-2" name="location" value="<?= h((string) ($item['location'] ?? '')) ?>">
<label class="form-label">Марка</label><input class="form-control mb-2" name="brand" value="<?= h((string) ($item['brand'] ?? '')) ?>">
<label class="form-label">Модель</label><input class="form-control mb-2" name="model" value="<?= h((string) ($item['model'] ?? '')) ?>">
<label class="form-label">SN внутреннего блока</label><input class="form-control mb-2" name="indoor_serial" value="<?= h((string) ($extraData['indoor_serial'] ?? '')) ?>">
<label class="form-label">SN наружного блока</label><input class="form-control mb-3" name="outdoor_serial" value="<?= h((string) ($extraData['outdoor_serial'] ?? '')) ?>">
<button class="btn btn-primary">Сохранить</button>
</form>
<?php if ($item): ?>
<hr>
<h2 class="h5">Фото по элементу</h2>
<form method="post" action="/photo_upload.php" enctype="multipart/form-data" class="card card-body mt-2">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
<input type="hidden" name="installation_id" value="<?= (int) $installationId ?>">
<input type="hidden" name="installation_item_id" value="<?= (int) $itemId ?>">
<input type="hidden" name="scope" value="item">
<div class="mb-2"><label class="form-label">Код фото</label><input name="photo_code" class="form-control" value="general"></div>
<div class="mb-2"><label class="form-label">Название</label><input name="title" class="form-control" value="Фото элемента"></div>
<div class="mb-3"><input type="file" name="photo" accept="image/*" capture="environment" class="form-control" required></div>
<button class="btn btn-success">Загрузить фото</button>
</form>
<div class="row mt-3">
<?php foreach ($photos as $photo): ?>
<div class="col-6 mb-3"><div class="card"><img src="/download_photo.php?id=<?= (int)$photo['id'] ?>" class="card-img-top" alt="thumb"><div class="card-body p-2"><div class="small"><?= h((string)$photo['title']) ?></div><a class="btn btn-sm btn-outline-danger mt-2" href="/photo_delete.php?id=<?= (int)$photo['id'] ?>">Удалить</a></div></div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div></body></html>
