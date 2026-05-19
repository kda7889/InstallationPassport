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
    redirect('/installation_item_edit.php?id=' . $newId);
}

$extraData = $item ? json_decode((string) ($item['extra_data_json'] ?? '{}'), true) : [];

$photos = [];
$photosByCode = [];
if ($item) {
    $photosStmt = db()->prepare('SELECT * FROM installation_photos WHERE installation_item_id = :item_id ORDER BY uploaded_at DESC');
    $photosStmt->execute(['item_id' => $itemId]);
    $photos = $photosStmt->fetchAll();
    foreach ($photos as $p) {
        $photosByCode[$p['photo_code']][] = $p;
    }
}

$tplStmt = db()->prepare("SELECT * FROM photo_templates WHERE work_type_id = :wt AND scope = 'item' AND is_active = 1 ORDER BY sort_order, id");
$tplStmt->execute(['wt' => $installation['work_type_id']]);
$templates = $tplStmt->fetchAll();
$templateCodes = array_column($templates, 'code');

$otherPhotos = [];
foreach ($photos as $p) {
    if (!in_array($p['photo_code'], $templateCodes, true)) {
        $otherPhotos[] = $p;
    }
}

$renderPhotoCard = static function (array $photo): string {
    $id = (int) $photo['id'];
    $title = h((string) ($photo['title'] ?? ''));
    $csrf = h(csrf_token());
    return <<<HTML
<div class="col-6 col-md-4 mb-2">
    <div class="card h-100 shadow-sm">
        <img src="/download_photo.php?id={$id}" class="card-img-top" alt="">
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

<?php if ($templates): ?>
    <h2 class="h5 mt-4 mb-2">Фотоотчёт по элементу</h2>
    <p class="text-muted small">Снимки с пометкой «рекомендуется» обычно ждут гарантийщики. Можно загружать в любом порядке.</p>

    <?php foreach ($templates as $tpl): ?>
        <?php $tplPhotos = $photosByCode[$tpl['code']] ?? []; ?>
        <div class="card mb-3 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-start mb-2">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">
                            <?= h((string) $tpl['title']) ?>
                            <?php if ((int) $tpl['is_important'] === 1): ?>
                                <span class="badge bg-warning text-dark ms-1">рекомендуется</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($tpl['description'])): ?>
                            <div class="small text-muted"><?= h((string) $tpl['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge <?= $tplPhotos ? 'bg-success' : 'bg-secondary' ?>"><?= count($tplPhotos) ?></span>
                </div>

                <?php if ($tplPhotos): ?>
                    <div class="row g-2 mb-2">
                        <?php foreach ($tplPhotos as $p) {
                            echo $renderPhotoCard($p);
                        } ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/photo_upload.php" enctype="multipart/form-data" class="d-flex gap-2">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="installation_id" value="<?= (int) $installationId ?>">
                    <input type="hidden" name="installation_item_id" value="<?= (int) $itemId ?>">
                    <input type="hidden" name="scope" value="item">
                    <input type="hidden" name="photo_code" value="<?= h((string) $tpl['code']) ?>">
                    <input type="hidden" name="title" value="<?= h((string) $tpl['title']) ?>">
                    <input type="file" name="photo" accept="image/*" class="form-control" required>
                    <button class="btn btn-success" type="submit">Добавить</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="fw-semibold mb-2">Дополнительные фото</div>

            <?php if ($otherPhotos): ?>
                <div class="row g-2 mb-2">
                    <?php foreach ($otherPhotos as $p) {
                        echo $renderPhotoCard($p);
                    } ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/photo_upload.php" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="installation_id" value="<?= (int) $installationId ?>">
                <input type="hidden" name="installation_item_id" value="<?= (int) $itemId ?>">
                <input type="hidden" name="scope" value="item">
                <input type="hidden" name="photo_code" value="other">

                <div class="mb-2">
                    <input name="title" class="form-control" placeholder="Подпись (необязательно)">
                </div>
                <div class="d-flex gap-2">
                    <input type="file" name="photo" accept="image/*" class="form-control" required>
                    <button class="btn btn-success" type="submit">Загрузить</button>
                </div>
            </form>
        </div>
    </div>

    <form method="post" action="/installation_item_delete.php" class="mt-4" onsubmit="return confirm('Удалить элемент со всеми его фото?')">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int) $itemId ?>">
        <button class="btn btn-outline-danger w-100" type="submit">Удалить элемент</button>
    </form>

<?php endif; ?>

</div>
</body>
</html>
