<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$number = trim((string) ($_GET['n'] ?? ''));
$code = trim((string) ($_GET['c'] ?? ''));

$installation = null;
$items = [];
$commonPhotos = [];
$itemPhotosMap = [];
$status = 'idle';

if ($number !== '' && $code !== '') {
    $stmt = db()->prepare('SELECT i.*, w.name AS work_type_name FROM installations i JOIN work_types w ON w.id = i.work_type_id WHERE i.number = :number LIMIT 1');
    $stmt->execute(['number' => $number]);
    $row = $stmt->fetch();

    if ($row && is_string($row['verification_code']) && hash_equals((string) $row['verification_code'], $code)) {
        $installation = $row;
        $status = 'ok';

        $itemStmt = db()->prepare('SELECT * FROM installation_items WHERE installation_id = :id ORDER BY sort_order, id');
        $itemStmt->execute(['id' => $row['id']]);
        $items = $itemStmt->fetchAll();

        $commonStmt = db()->prepare("SELECT * FROM installation_photos WHERE installation_id = :id AND scope = 'common' ORDER BY uploaded_at");
        $commonStmt->execute(['id' => $row['id']]);
        $commonPhotos = $commonStmt->fetchAll();

        $itemPhotosMap = array_fill_keys(array_map('intval', array_column($items, 'id')), []);
        if ($items) {
            $ids = array_map('intval', array_column($items, 'id'));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $ps = db()->prepare("SELECT * FROM installation_photos WHERE installation_item_id IN ($placeholders) ORDER BY uploaded_at");
            $ps->execute($ids);
            foreach ($ps->fetchAll() as $p) {
                $itemPhotosMap[(int) $p['installation_item_id']][] = $p;
            }
        }
    } else {
        $status = 'fail';
    }
}

$branding = company_branding();
$root = dirname(__DIR__);
$photoUrl = static function (array $photo) use ($root): string {
    $rel = (string) ($photo['file_path'] ?? '');
    return '/photo_public.php?n=' . urlencode((string) ($_GET['n'] ?? '')) . '&c=' . urlencode((string) ($_GET['c'] ?? '')) . '&p=' . (int) $photo['id'];
};
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Гарантийный талон <?= h($number) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">

    <?php if (!empty($branding['name']) || !empty($branding['logo_path'])): ?>
        <div class="card card-body shadow-sm mb-3">
            <div class="d-flex align-items-center">
                <?php if (!empty($branding['logo_path'])): ?>
                    <img src="/<?= h((string) $branding['logo_path']) ?>" alt="" style="max-height:60px; margin-right:12px;">
                <?php endif; ?>
                <div>
                    <?php if (!empty($branding['name'])): ?>
                        <div class="fw-semibold"><?= h((string) $branding['name']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($branding['phone']) || !empty($branding['email'])): ?>
                        <div class="small text-muted">
                            <?= h((string) $branding['phone']) ?>
                            <?php if (!empty($branding['email'])): ?> · <?= h((string) $branding['email']) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <h1 class="h4 mb-3">Гарантийный талон</h1>

    <?php if ($status === 'idle'): ?>
        <div class="card card-body shadow-sm">
            <p class="text-muted">Введите номер и код проверки из PDF гарантийного талона.</p>
            <form method="get">
                <label class="form-label">Номер</label>
                <input class="form-control mb-2" name="n" placeholder="MP-20260519-A3F4E2" required>
                <label class="form-label">Код проверки</label>
                <input class="form-control mb-3" name="c" required>
                <button class="btn btn-primary">Открыть</button>
            </form>
        </div>

    <?php elseif ($status === 'fail'): ?>
        <div class="alert alert-danger">
            <div class="fw-semibold">Документ не найден</div>
            <div class="small">Проверьте номер и код. Если они напечатаны верно — это не наш гарантийный талон.</div>
        </div>

    <?php elseif ($status === 'ok' && $installation): ?>
        <div class="alert alert-success">
            <span class="fw-semibold">Документ подлинный ✓</span>
        </div>

        <div class="card card-body shadow-sm mb-3">
            <div class="mb-1"><span class="text-muted">Номер:</span> <strong><?= h((string) $installation['number']) ?></strong></div>
            <div class="mb-1"><span class="text-muted">Дата монтажа:</span> <?= h(date('d.m.Y', strtotime((string) ($installation['install_date'] ?: 'today')))) ?></div>
            <div class="mb-1"><span class="text-muted">Тип работ:</span> <?= h((string) $installation['work_type_name']) ?></div>
            <div class="mb-1"><span class="text-muted">Адрес:</span> <?= h((string) $installation['address']) ?></div>
            <?php if (!empty($installation['customer_name'])): ?>
                <div class="mb-1"><span class="text-muted">Заказчик:</span> <?= h((string) $installation['customer_name']) ?></div>
            <?php endif; ?>
            <div class="mb-1">
                <span class="text-muted">Гарантия:</span> <?= (int) $installation['warranty_months'] ?> мес.
                <?php if (!empty($installation['warranty_until'])): ?>
                    , действует до <strong><?= h(date('d.m.Y', strtotime((string) $installation['warranty_until']))) ?></strong>
                <?php endif; ?>
            </div>
            <?php if (!empty($installation['work_description'])): ?>
                <div class="mt-2"><span class="text-muted">Описание работ:</span><br><?= nl2br(h((string) $installation['work_description'])) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($items): ?>
            <h2 class="h5 mt-4 mb-2">Установленное оборудование</h2>
            <div class="list-group mb-3">
                <?php foreach ($items as $idx => $item): ?>
                    <div class="list-group-item">
                        <div class="fw-semibold"><?= $idx + 1 ?>. <?= h((string) $item['title']) ?></div>
                        <?php if (!empty($item['location'])): ?>
                            <div class="small text-muted">Место: <?= h((string) $item['location']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['brand']) || !empty($item['model'])): ?>
                            <div class="small text-muted">
                                <?php if (!empty($item['brand'])): ?>Марка: <?= h((string) $item['brand']) ?><?php endif; ?>
                                <?php if (!empty($item['model'])): ?> · Модель: <?= h((string) $item['model']) ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($commonPhotos): ?>
            <h2 class="h5 mt-4 mb-2">Фото объекта</h2>
            <div class="row g-2 mb-3">
                <?php foreach ($commonPhotos as $p): ?>
                    <div class="col-6 col-md-4">
                        <div class="card">
                            <a href="<?= h($photoUrl($p)) ?>&full=1" target="_blank">
                                <img src="<?= h($photoUrl($p)) ?>" class="card-img-top" alt="">
                            </a>
                            <div class="card-body p-2 small"><?= h((string) ($p['title'] ?? '')) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($items as $item): ?>
            <?php $photos = $itemPhotosMap[(int) $item['id']] ?? []; ?>
            <?php if ($photos): ?>
                <h2 class="h6 mt-3 mb-2">Фото: <?= h((string) $item['title']) ?></h2>
                <div class="row g-2 mb-3">
                    <?php foreach ($photos as $p): ?>
                        <div class="col-6 col-md-4">
                            <div class="card">
                                <a href="<?= h($photoUrl($p)) ?>&full=1" target="_blank">
                                    <img src="<?= h($photoUrl($p)) ?>" class="card-img-top" alt="">
                                </a>
                                <div class="card-body p-2 small"><?= h((string) ($p['title'] ?? '')) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
</body>
</html>
