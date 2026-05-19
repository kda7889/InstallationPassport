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
    $installDate = trim((string) post('install_date', ''));
    if ($installDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $installDate)) {
        $installDate = (string) ($installation['install_date'] ?? '');
    }

    $address = trim((string) post('address', ''));
    if ($address === '') {
        $address = (string) ($installation['address'] ?? '');
    }

    $warrantyMonths = max(1, (int) post('warranty_months', '12'));
    $warrantyUntil = null;
    if ($installDate !== '') {
        try {
            $warrantyUntil = (new DateTimeImmutable($installDate))->modify('+' . $warrantyMonths . ' months')->format('Y-m-d');
        } catch (Throwable $e) {
            $warrantyUntil = null;
        }
    }

    $allowedStatuses = ['draft', 'in_progress', 'photos_partial', 'ready', 'pdf_generated', 'closed'];
    $status = (string) post('status', 'draft');
    if (!in_array($status, $allowedStatuses, true)) {
        $status = (string) ($installation['status'] ?? 'draft');
    }

    $upd = db()->prepare('UPDATE installations SET install_date=:install_date, address=:address, customer_name=:customer_name, customer_phone=:customer_phone, installer_name=:installer_name, installer_phone=:installer_phone, company_name=:company_name, company_inn=:company_inn, warranty_months=:warranty_months, warranty_until=:warranty_until, work_description=:work_description, comment=:comment, status=:status, updated_at=:updated_at WHERE id=:id');
    $upd->execute([
        'install_date' => $installDate,
        'address' => $address,
        'customer_name' => (string) post('customer_name', ''),
        'customer_phone' => (string) post('customer_phone', ''),
        'installer_name' => (string) post('installer_name', ''),
        'installer_phone' => (string) post('installer_phone', ''),
        'company_name' => (string) post('company_name', ''),
        'company_inn' => (string) post('company_inn', ''),
        'warranty_months' => $warrantyMonths,
        'warranty_until' => $warrantyUntil,
        'work_description' => (string) post('work_description', ''),
        'comment' => (string) post('comment', ''),
        'status' => $status,
        'updated_at' => now(),
        'id' => $id,
    ]);
    audit_log('installation.updated', 'installation', $id);
    redirect('/installation_edit.php?id=' . $id);
}

$itemStmt = db()->prepare('SELECT * FROM installation_items WHERE installation_id = :id ORDER BY sort_order, id');
$itemStmt->execute(['id' => $id]);
$items = $itemStmt->fetchAll();

$commonPhotosStmt = db()->prepare("SELECT * FROM installation_photos WHERE installation_id = :id AND scope = 'common' ORDER BY uploaded_at DESC");
$commonPhotosStmt->execute(['id' => $id]);
$commonPhotos = $commonPhotosStmt->fetchAll();

$commonByCode = [];
foreach ($commonPhotos as $p) {
    $commonByCode[$p['photo_code']][] = $p;
}

$commonTplStmt = db()->prepare("SELECT * FROM photo_templates WHERE work_type_id = :wt AND scope = 'common' AND is_active = 1 ORDER BY sort_order, id");
$commonTplStmt->execute(['wt' => $installation['work_type_id']]);
$commonTemplates = $commonTplStmt->fetchAll();
$commonTemplateCodes = array_column($commonTemplates, 'code');

$otherCommonPhotos = [];
foreach ($commonPhotos as $p) {
    if (!in_array($p['photo_code'], $commonTemplateCodes, true)) {
        $otherCommonPhotos[] = $p;
    }
}

$warningStmt = db()->prepare("SELECT code, title FROM photo_templates WHERE work_type_id = :wt AND scope = 'item' AND is_important = 1 AND is_active = 1 ORDER BY sort_order");
$warningStmt->execute(['wt' => $installation['work_type_id']]);
$importantTemplates = $warningStmt->fetchAll();

$codesByItem = [];
if ($items && $importantTemplates) {
    $itemIds = array_map('intval', array_column($items, 'id'));
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $codesStmt = db()->prepare("SELECT installation_item_id, photo_code FROM installation_photos WHERE installation_item_id IN ($placeholders)");
    $codesStmt->execute($itemIds);
    foreach ($codesStmt->fetchAll() as $row) {
        $codesByItem[(int) $row['installation_item_id']][] = $row['photo_code'];
    }
}
$missingImportant = [];
foreach ($items as $it) {
    $codes = $codesByItem[(int) $it['id']] ?? [];
    foreach ($importantTemplates as $tpl) {
        if (!in_array($tpl['code'], $codes, true)) {
            $missingImportant[] = $it['title'] . ': ' . $tpl['title'];
        }
    }
}

$statusLabels = [
    'draft' => 'Черновик',
    'in_progress' => 'В работе',
    'photos_partial' => 'Фото частично',
    'ready' => 'Готов к PDF',
    'pdf_generated' => 'PDF сформирован',
    'closed' => 'Закрыт',
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
    <title><?= h((string) $installation['number']) ?> — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">

    <a href="/dashboard.php" class="btn btn-link px-0">← К списку</a>

    <h1 class="h4"><?= h((string) $installation['number']) ?></h1>
    <p class="text-muted"><?= h((string) $installation['work_type_name']) ?> · <?= h((string) $installation['address']) ?></p>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary" href="/generate_pdf.php?id=<?= (int) $installation['id'] ?>">Сформировать PDF</a>
        <?php if (!empty($installation['pdf_path'])): ?>
            <a class="btn btn-outline-success" href="/download_pdf.php?id=<?= (int) $installation['id'] ?>">Скачать PDF</a>
        <?php endif; ?>
    </div>

    <?php if ($missingImportant): ?>
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Рекомендованные фото ещё не загружены:</div>
            <ul class="mb-0">
                <?php foreach ($missingImportant as $m): ?>
                    <li><?= h($m) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card card-body mb-3 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <h2 class="h6 text-muted mb-3">Объект</h2>
        <label class="form-label">Адрес</label>
        <textarea class="form-control mb-3" rows="2" name="address" required><?= h((string) ($installation['address'] ?? '')) ?></textarea>

        <h2 class="h6 text-muted mb-3">Заказчик</h2>
        <label class="form-label">Имя</label>
        <input class="form-control mb-2" name="customer_name" value="<?= h((string) ($installation['customer_name'] ?? '')) ?>">

        <label class="form-label">Телефон</label>
        <input class="form-control mb-3" type="tel" name="customer_phone" value="<?= h((string) ($installation['customer_phone'] ?? '')) ?>">

        <h2 class="h6 text-muted mb-3">Исполнитель</h2>
        <label class="form-label">Имя</label>
        <input class="form-control mb-2" name="installer_name" value="<?= h((string) ($installation['installer_name'] ?? '')) ?>">

        <label class="form-label">Телефон</label>
        <input class="form-control mb-2" type="tel" name="installer_phone" value="<?= h((string) ($installation['installer_phone'] ?? '')) ?>">

        <label class="form-label">Организация / ИП</label>
        <input class="form-control mb-2" name="company_name" value="<?= h((string) ($installation['company_name'] ?? '')) ?>">

        <label class="form-label">ИНН / ОГРН</label>
        <input class="form-control mb-3" name="company_inn" value="<?= h((string) ($installation['company_inn'] ?? '')) ?>">

        <h2 class="h6 text-muted mb-3">Гарантия и работы</h2>
        <label class="form-label">Дата монтажа</label>
        <input class="form-control mb-2" type="date" name="install_date" value="<?= h((string) ($installation['install_date'] ?? '')) ?>">

        <label class="form-label">Срок гарантии (мес.)</label>
        <input class="form-control mb-2" type="number" min="1" name="warranty_months" value="<?= h((string) ($installation['warranty_months'] ?? 12)) ?>">

        <?php if (!empty($installation['warranty_until'])): ?>
            <div class="small text-muted mb-2">Гарантия действует до <strong><?= h(date('d.m.Y', strtotime((string) $installation['warranty_until']))) ?></strong></div>
        <?php endif; ?>

        <label class="form-label">Описание работ</label>
        <textarea class="form-control mb-2" rows="3" name="work_description"><?= h((string) ($installation['work_description'] ?? '')) ?></textarea>

        <label class="form-label">Комментарий</label>
        <textarea class="form-control mb-3" rows="2" name="comment"><?= h((string) ($installation['comment'] ?? '')) ?></textarea>

        <label class="form-label">Статус</label>
        <select name="status" class="form-select mb-3">
            <?php foreach ($statusLabels as $code => $label): ?>
                <option value="<?= h($code) ?>" <?= (($installation['status'] ?? '') === $code ? 'selected' : '') ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" type="submit">Сохранить</button>
    </form>

    <h2 class="h5 mb-2">Общие фото объекта</h2>

    <?php foreach ($commonTemplates as $tpl): ?>
        <?php $tplPhotos = $commonByCode[$tpl['code']] ?? []; ?>
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
                    <input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>">
                    <input type="hidden" name="scope" value="common">
                    <input type="hidden" name="photo_code" value="<?= h((string) $tpl['code']) ?>">
                    <input type="hidden" name="title" value="<?= h((string) $tpl['title']) ?>">
                    <input type="file" name="photo" accept="image/*" class="form-control" required>
                    <button class="btn btn-success" type="submit">Добавить</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="fw-semibold mb-2">
                <?= $commonTemplates ? 'Прочие общие фото' : 'Общие фото объекта' ?>
            </div>

            <?php if ($otherCommonPhotos): ?>
                <div class="row g-2 mb-2">
                    <?php foreach ($otherCommonPhotos as $p) {
                        echo $renderPhotoCard($p);
                    } ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/photo_upload.php" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>">
                <input type="hidden" name="scope" value="common">
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

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Элементы монтажа</h2>
        <a class="btn btn-sm btn-primary" href="/installation_item_edit.php?installation_id=<?= (int) $installation['id'] ?>">+ Добавить</a>
    </div>

    <div class="list-group mb-4">
        <?php foreach ($items as $it): ?>
            <a href="/installation_item_edit.php?id=<?= (int) $it['id'] ?>" class="list-group-item list-group-item-action">
                <div class="fw-semibold"><?= h((string) $it['title']) ?></div>
                <?php if (!empty($it['location'])): ?>
                    <div class="small text-muted"><?= h((string) $it['location']) ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (!$items): ?>
            <div class="list-group-item text-muted">Элементов пока нет.</div>
        <?php endif; ?>
    </div>

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
