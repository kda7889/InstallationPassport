<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$number = mb_strtoupper(trim((string) ($_GET['n'] ?? '')));
$code = strtolower(trim((string) ($_GET['c'] ?? '')));

$installation = null;
$accessLevel = 'none'; // none / public / personal
$photos = [];

if ($number !== '' && $code !== '') {
    $stmt = db()->prepare('SELECT i.*, w.name AS work_type_name FROM installations i JOIN work_types w ON w.id = i.work_type_id WHERE i.number = :number LIMIT 1');
    $stmt->execute(['number' => $number]);
    $row = $stmt->fetch();

    if ($row) {
        $verify = (string) ($row['verification_code'] ?? '');
        $access = (string) ($row['access_token'] ?? '');
        if ($access !== '' && hash_equals($access, $code)) {
            $accessLevel = 'personal';
            $installation = $row;
        } elseif ($verify !== '' && hash_equals($verify, $code)) {
            $accessLevel = 'public';
            $installation = $row;
        }
    }
}

if ($installation !== null) {
    $commonStmt = db()->prepare("SELECT * FROM installation_photos WHERE installation_id = :id AND scope = 'common' ORDER BY photo_stage, uploaded_at");
    $commonStmt->execute(['id' => $installation['id']]);
    $photos = $commonStmt->fetchAll();
}

$branding = $installation ? company_branding((int) ($installation['company_id'] ?? 0)) : ['name' => '', 'logo_path' => '', 'phone' => '', 'email' => '', 'inn' => '', 'address' => ''];
$photoUrl = static function (array $photo) use ($number, $code): string {
    return '/photo_public.php?n=' . urlencode($number) . '&c=' . urlencode($code) . '&p=' . (int) $photo['id'];
};

$reviews = [];
$ratingsByReview = [];
if ($installation !== null) {
    $rev = db()->prepare('SELECT * FROM reviews WHERE installation_id = :id AND is_hidden = 0 ORDER BY created_at DESC');
    $rev->execute(['id' => $installation['id']]);
    $reviews = $rev->fetchAll();
    if ($reviews) {
        $rIds = array_map(static fn($r) => (int) $r['id'], $reviews);
        $ph = implode(',', array_fill(0, count($rIds), '?'));
        $rr = db()->prepare("SELECT * FROM review_ratings WHERE review_id IN ($ph)");
        $rr->execute($rIds);
        foreach ($rr->fetchAll() as $r) {
            $ratingsByReview[(int) $r['review_id']][(string) $r['criterion']] = (int) $r['stars'];
        }
    }
}

$criteria = review_criteria();
$periodLabels = [
    'initial' => 'Сразу после монтажа',
    '1m' => 'Через месяц',
    '1y' => 'Через год',
    '2y' => 'Через 2 года',
    '3y' => 'Через 3 года',
    'custom' => 'Дополнительно',
];

$stageLabels = [
    'before' => 'До работ',
    'during' => 'В процессе',
    'after' => 'После работ',
    'other' => 'Прочее',
];

$customerName = (string) ($installation['customer_name'] ?? '');
$customerPhone = (string) ($installation['customer_phone'] ?? '');
$address = (string) ($installation['address'] ?? '');
if ($accessLevel === 'public') {
    $customerName = mask_name($customerName);
    $customerPhone = mask_phone($customerPhone);
    $address = mask_address($address);
}

$warrantyUntil = (string) ($installation['warranty_until'] ?? '');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Гарантийный талон <?= h($number) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .star { color: #ffc107; }
        .star.empty { color: #ddd; }
        .star-input input { display: none; }
        .star-input label { font-size: 28px; cursor: pointer; color: #ddd; }
        .star-input input:checked ~ label,
        .star-input label:hover,
        .star-input label:hover ~ label { color: #ffc107; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">

<?php if ($accessLevel === 'none'): ?>
    <h1 class="h4 mb-3">Найти гарантийный талон</h1>
    <div class="card card-body shadow-sm">
        <p class="text-muted small">Номер и код доступа напечатаны на вашем PDF-гарантийнике.</p>
        <form method="get">
            <label class="form-label">Номер документа</label>
            <input class="form-control mb-2" name="n" value="<?= h($number) ?>" placeholder="MP-20260519-A3F4E2" required>
            <label class="form-label">Код доступа</label>
            <input class="form-control mb-3" name="c" value="<?= h($code) ?>" required>
            <button class="btn btn-primary">Открыть</button>
        </form>
        <?php if ($number !== '' && $code !== ''): ?>
            <div class="alert alert-danger mt-3 mb-0">Документ не найден. Проверьте номер и код.</div>
        <?php endif; ?>
    </div>
<?php else: ?>

    <?php if (!empty($branding['name']) || !empty($branding['logo_path'])): ?>
        <div class="card card-body shadow-sm mb-3">
            <div class="d-flex align-items-center">
                <?php if (!empty($branding['logo_path'])): ?>
                    <img src="/branding_logo.php?company=<?= (int) $branding['id'] ?>" alt="" style="max-height:60px; margin-right:12px;">
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

    <h1 class="h4 mb-2">Гарантийный талон</h1>
    <div class="alert <?= $accessLevel === 'personal' ? 'alert-success' : 'alert-info' ?> mb-3">
        Документ подлинный ✓
        <?php if ($accessLevel === 'public'): ?>
            <div class="small mt-1">Это публичная проверка. Личные данные заказчика скрыты по 152-ФЗ. Полный доступ — по коду из PDF.</div>
        <?php endif; ?>
    </div>

    <div class="card card-body shadow-sm mb-3">
        <div class="mb-1"><span class="text-muted">Номер:</span> <strong><?= h((string) $installation['number']) ?></strong></div>
        <div class="mb-1"><span class="text-muted">Дата монтажа:</span> <?= h(date('d.m.Y', strtotime((string) ($installation['install_date'] ?: 'today')))) ?></div>
        <div class="mb-1"><span class="text-muted">Тип работ:</span> <?= h((string) $installation['work_type_name']) ?></div>
        <div class="mb-1"><span class="text-muted">Адрес:</span> <?= h($address) ?></div>
        <?php if ($customerName !== ''): ?>
            <div class="mb-1"><span class="text-muted">Заказчик:</span> <?= h($customerName) ?></div>
        <?php endif; ?>
        <?php if ($accessLevel === 'personal' && $customerPhone !== ''): ?>
            <div class="mb-1"><span class="text-muted">Телефон:</span> <?= h($customerPhone) ?></div>
        <?php endif; ?>
        <div class="mb-1">
            <span class="text-muted">Гарантия:</span> <?= (int) $installation['warranty_months'] ?> мес.
            <?php if ($warrantyUntil): ?>, действует до <strong><?= h(date('d.m.Y', strtotime($warrantyUntil))) ?></strong><?php endif; ?>
        </div>
        <?php if (!empty($installation['work_description'])): ?>
            <div class="mt-2"><span class="text-muted">Описание работ:</span><br><?= nl2br(h((string) $installation['work_description'])) ?></div>
        <?php endif; ?>
    </div>

    <?php
    $renderPhotos = static function (array $photos) use ($photoUrl, $stageLabels): void {
        $byStage = ['before' => [], 'during' => [], 'after' => [], 'other' => []];
        foreach ($photos as $p) {
            $stage = (string) ($p['photo_stage'] ?? 'other');
            if (!isset($byStage[$stage])) {
                $stage = 'other';
            }
            $byStage[$stage][] = $p;
        }
        foreach ($byStage as $stage => $rows) {
            if (!$rows) continue;
            echo '<div class="fw-semibold mt-3 mb-2">' . h($stageLabels[$stage]) . '</div>';
            echo '<div class="row g-2 mb-2">';
            foreach ($rows as $p) {
                $url = h($photoUrl($p));
                $title = h((string) ($p['title'] ?? ''));
                echo '<div class="col-6 col-md-4"><div class="card"><a href="' . $url . '&full=1" target="_blank"><img src="' . $url . '" class="card-img-top" alt=""></a><div class="card-body p-2 small">' . $title . '</div></div></div>';
            }
            echo '</div>';
        }
    };
    ?>

    <?php if ($photos): ?>
        <h2 class="h5 mt-4 mb-2">Фото объекта</h2>
        <?php $renderPhotos($photos); ?>
    <?php endif; ?>

    <h2 class="h5 mt-4 mb-2">Отзывы</h2>
    <?php if (!$reviews): ?>
        <div class="text-muted small mb-3">Отзывов ещё нет.</div>
    <?php else: ?>
        <?php foreach ($reviews as $r):
            $rid = (int) $r['id'];
            $rrs = $ratingsByReview[$rid] ?? [];
            $overall = (int) ($r['overall_rating'] ?? 0);
            ?>
            <div class="card card-body shadow-sm mb-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?= $i <= $overall ? '' : 'empty' ?>">★</span>
                            <?php endfor; ?>
                        </strong>
                        <?php
                            $reviewName = (string) ($r['customer_name_provided'] ?? '');
                            if ($reviewName === '') {
                                $reviewName = (string) ($installation['customer_name'] ?? '');
                            }
                            if ($accessLevel === 'public') {
                                $reviewName = mask_name($reviewName);
                            }
                        ?>
                        <span class="text-muted small ms-1"><?= h($reviewName) ?></span>
                    </div>
                    <div class="small text-muted">
                        <?= h($periodLabels[$r['period_label']] ?? (string) $r['period_label']) ?> · <?= h(date('d.m.Y', strtotime((string) $r['created_at']))) ?>
                    </div>
                </div>
                <?php if (!empty($r['text'])): ?>
                    <div class="mb-2"><?= nl2br(h((string) $r['text'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($r['suggestions'])): ?>
                    <div class="small text-muted">Предложения: <?= nl2br(h((string) $r['suggestions'])) ?></div>
                <?php endif; ?>
                <?php if ($rrs): ?>
                    <details class="small text-muted mt-1">
                        <summary>Подробнее по критериям</summary>
                        <?php foreach ($criteria as $cKey => $cName): ?>
                            <?php if (isset($rrs[$cKey])): ?>
                                <div><?= h($cName) ?>:
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?= $i <= $rrs[$cKey] ? '' : 'empty' ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </details>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($accessLevel === 'personal'): ?>
        <a class="btn btn-primary mt-3" href="/review_submit.php?n=<?= urlencode($number) ?>&c=<?= urlencode($code) ?>">Оставить отзыв</a>
    <?php else: ?>
        <div class="alert alert-secondary small mt-3">Чтобы оставить отзыв, откройте талон по личному коду доступа (он указан в вашем PDF).</div>
    <?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
