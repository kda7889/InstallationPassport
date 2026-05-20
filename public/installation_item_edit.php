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
        http_response_code(404);
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
        $upd->execute(['title' => $title, 'location' => $location, 'brand' => $brand, 'model' => $model, 'extra' => $extra, 'updated_at' => now(), 'id' => $itemId]);
        audit_log('item.updated', 'installation_item', $itemId, ['installation_id' => $installationId]);
        redirect('/installation_item_edit.php?id=' . $itemId);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare('INSERT INTO installation_items (installation_id, work_type_id, item_number, title, location, brand, model, extra_data_json, created_at, updated_at) VALUES (:installation_id, :work_type_id, :item_number, :title, :location, :brand, :model, :extra, :created_at, :updated_at)');
        $ins->execute([
            'installation_id' => $installationId,
            'work_type_id' => $installation['work_type_id'],
            'item_number' => '_pending_',
            'title' => $title !== '' ? $title : 'Новый элемент',
            'location' => $location,
            'brand' => $brand,
            'model' => $model,
            'extra' => $extra,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $newId = (int) $pdo->lastInsertId();
        $itemNumber = sprintf('item-%d', $newId);
        $pdo->prepare('UPDATE installation_items SET item_number = :item_number, title = :title WHERE id = :id')
            ->execute([
                'item_number' => $itemNumber,
                'title' => $title !== '' ? $title : ('Элемент #' . $newId),
                'id' => $newId,
            ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    audit_log('item.created', 'installation_item', $newId, ['installation_id' => $installationId]);
    redirect('/installation_item_edit.php?id=' . $newId);
}

$extraData = $item ? json_decode((string) ($item['extra_data_json'] ?? '{}'), true) : [];

$photosByStage = ['before' => [], 'during' => [], 'after' => [], 'other' => []];
if ($item) {
    $photosStmt = db()->prepare('SELECT * FROM installation_photos WHERE installation_item_id = :item_id ORDER BY uploaded_at DESC');
    $photosStmt->execute(['item_id' => $itemId]);
    foreach ($photosStmt->fetchAll() as $p) {
        $stage = (string) ($p['photo_stage'] ?? 'other');
        if (!isset($photosByStage[$stage])) {
            $stage = 'other';
        }
        $photosByStage[$stage][] = $p;
    }
}

$stageLabels = [
    'before' => 'До работ',
    'during' => 'В процессе',
    'after' => 'После работ',
    'other' => 'Прочее',
];

$renderPhotoCard = static function (array $photo): string {
    $id = (int) $photo['id'];
    $title = h((string) ($photo['title'] ?? ''));
    $csrf = h(csrf_token());
    return <<<HTML
<div class="col-6 col-md-4 mb-2">
    <div class="card h-100 shadow-sm">
        <img src="/download_photo.php?id={$id}" class="card-img-top photo-zoom" data-full="/download_photo.php?id={$id}&size=full" data-title="{$title}" style="cursor:zoom-in;" alt="">
        <div class="card-body p-2">
            <div class="small text-truncate mb-1" title="{$title}">{$title}</div>
            <form method="post" action="/photo_delete.php" onsubmit="return confirm('Удалить фото?')">
                <input type="hidden" name="_csrf" value="{$csrf}">
                <input type="hidden" name="id" value="{$id}">
                <button class="btn btn-sm btn-outline-danger w-100" type="submit">Удалить</button>
            </form>
        </div>
    </div>
</div>
HTML;
};
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $item ? h((string) $item['title']) : 'Новый элемент' ?> — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">

    <a href="/installation_edit.php?id=<?= (int) $installationId ?>" class="btn btn-link px-0">← К монтажу</a>
    <h1 class="h4 mb-3"><?= $item ? 'Элемент: ' . h((string) $item['title']) : 'Новый элемент' ?></h1>

    <form method="post" class="card card-body mb-3 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <label class="form-label">Название элемента</label>
        <input class="form-control mb-2" name="title" value="<?= h((string) ($item['title'] ?? '')) ?>" required>

        <label class="form-label">Место установки</label>
        <input class="form-control mb-2" name="location" value="<?= h((string) ($item['location'] ?? '')) ?>" placeholder="например, кухня">

        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="form-label">Марка</label>
                <input class="form-control" name="brand" value="<?= h((string) ($item['brand'] ?? '')) ?>">
            </div>
            <div class="col-6">
                <label class="form-label">Модель</label>
                <input class="form-control" name="model" value="<?= h((string) ($item['model'] ?? '')) ?>">
            </div>
        </div>

        <label class="form-label">Серийный номер внутреннего блока</label>
        <input class="form-control mb-2" name="indoor_serial" value="<?= h((string) ($extraData['indoor_serial'] ?? '')) ?>">

        <label class="form-label">Серийный номер наружного блока</label>
        <input class="form-control mb-3" name="outdoor_serial" value="<?= h((string) ($extraData['outdoor_serial'] ?? '')) ?>">

        <button class="btn btn-primary" type="submit">Сохранить</button>
    </form>

<?php if ($item): ?>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Загрузить фото</h2>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#tips">Что снять?</button>
            </div>

            <div id="tips" class="collapse mt-2">
                <div class="alert alert-info small mb-2">
                    <div class="fw-semibold mb-1">📷 Что обычно стоит сфотографировать:</div>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>До работ</strong>
                            <ul class="mb-0">
                                <li>Состояние помещения</li>
                                <li>Существующая проводка / трубы</li>
                                <li>Место будущего монтажа</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>В процессе</strong>
                            <ul class="mb-0">
                                <li>Распакованное оборудование + шильдик</li>
                                <li>Сложные узлы монтажа</li>
                                <li>Опрессовка / вакуумирование</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <strong>После работ</strong>
                            <ul class="mb-0">
                                <li>Готовое оборудование общим планом</li>
                                <li>Шильдик / серийный номер крупно</li>
                                <li>Демонстрация работы</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" action="/photo_upload.php" enctype="multipart/form-data" class="mt-2">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="installation_id" value="<?= (int) $installationId ?>">
                <input type="hidden" name="installation_item_id" value="<?= (int) $itemId ?>">
                <input type="hidden" name="scope" value="item">
                <input type="hidden" name="photo_code" value="item_photo">

                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Стадия</label>
                        <select name="photo_stage" class="form-select">
                            <option value="before">До работ</option>
                            <option value="during">В процессе</option>
                            <option value="after" selected>После работ</option>
                            <option value="other">Прочее</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Подпись</label>
                        <input name="title" class="form-control" placeholder="например, шильдик">
                    </div>
                    <div class="col-12">
                        <input type="file" name="photo" accept="image/*" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success w-100" type="submit">Добавить фото</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($stageLabels as $stage => $label): ?>
        <?php $stagePhotos = $photosByStage[$stage] ?? []; ?>
        <?php if ($stagePhotos): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="fw-semibold flex-grow-1"><?= h($label) ?></div>
                        <span class="badge bg-success"><?= count($stagePhotos) ?></span>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($stagePhotos as $p) {
                            echo $renderPhotoCard($p);
                        } ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <form method="post" action="/installation_item_delete.php" class="mt-4" onsubmit="return confirm('Удалить элемент со всеми его фото?')">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $itemId ?>">
        <button class="btn btn-outline-danger w-100" type="submit">Удалить элемент</button>
    </form>

<?php endif; ?>

</div>

<div class="modal fade" id="photoLightbox" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-dark">
            <div class="modal-body p-2 text-center">
                <img id="photoLightboxImg" src="" alt="" style="max-width:100%; max-height:85vh;">
                <div id="photoLightboxTitle" class="text-light mt-2 small"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('click', function (e) {
    var t = e.target;
    if (t && t.classList && t.classList.contains('photo-zoom')) {
        document.getElementById('photoLightboxImg').src = t.dataset.full || t.src;
        document.getElementById('photoLightboxTitle').textContent = t.dataset.title || '';
        new bootstrap.Modal(document.getElementById('photoLightbox')).show();
    }
});
</script>
</body>
</html>
