<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
$user = current_user();

$isSuperadmin = !empty($user['is_superadmin']);
$companyId = (int) ($user['company_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate(post('_csrf'))) {
    $reviewId = (int) post('id', '0');
    $stmt = db()->prepare('SELECT r.id, i.company_id FROM reviews r JOIN installations i ON i.id = r.installation_id WHERE r.id = :id');
    $stmt->execute(['id' => $reviewId]);
    $row = $stmt->fetch();

    if ($row && ($isSuperadmin || (int) $row['company_id'] === $companyId)) {
        $action = (string) post('action', '');
        if ($action === 'hide') {
            db()->prepare('UPDATE reviews SET is_hidden = 1 WHERE id = :id')->execute(['id' => $reviewId]);
            audit_log('review.hidden', 'review', $reviewId);
        } elseif ($action === 'unhide') {
            db()->prepare('UPDATE reviews SET is_hidden = 0 WHERE id = :id')->execute(['id' => $reviewId]);
            audit_log('review.unhidden', 'review', $reviewId);
        }
        redirect('/reviews.php');
    }
}

$where = $isSuperadmin ? '' : 'WHERE i.company_id = :cid';
$params = $isSuperadmin ? [] : ['cid' => $companyId];
$stmt = db()->prepare("SELECT r.*, i.number AS installation_number, i.customer_name, u.name AS installer_name, c.name AS company_name FROM reviews r JOIN installations i ON i.id = r.installation_id LEFT JOIN users u ON u.id = i.user_id LEFT JOIN companies c ON c.id = i.company_id $where ORDER BY r.created_at DESC LIMIT 200");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$criteria = review_criteria();
$periodLabels = [
    'initial' => 'Сразу',
    '1m' => '+1 мес',
    '1y' => '+1 год',
    '2y' => '+2 года',
    '3y' => '+3 года',
    'custom' => 'Доп.',
];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Отзывы — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>.star{color:#ffc107}.star.empty{color:#ddd}</style>
</head>
<body class="bg-light">
<div class="container py-3">

    <a href="/dashboard.php" class="btn btn-link px-0">← К монтажам</a>
    <h1 class="h4 mb-3">Отзывы клиентов</h1>

    <?php if (!$reviews): ?>
        <div class="text-muted">Отзывов пока нет.</div>
    <?php endif; ?>

    <?php foreach ($reviews as $r): ?>
        <div class="card card-body shadow-sm mb-2">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= (int) $r['overall_rating'] ? '' : 'empty' ?>">★</span>
                        <?php endfor; ?>
                    </strong>
                    <span class="small text-muted ms-1"><?= h(mask_name((string) ($r['customer_name_provided'] ?? $r['customer_name'] ?? ''))) ?></span>
                </div>
                <div class="small text-muted">
                    <?= h($periodLabels[$r['period_label']] ?? $r['period_label']) ?> ·
                    <?= h(date('d.m.Y', strtotime((string) $r['created_at']))) ?>
                    <?php if ((int) $r['is_hidden'] === 1): ?>
                        <span class="badge bg-secondary ms-1">скрыт</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="small text-muted mb-1">
                Талон <code><?= h((string) $r['installation_number']) ?></code>
                <?php if (!empty($r['installer_name'])): ?>· монтажник: <?= h((string) $r['installer_name']) ?><?php endif; ?>
                <?php if ($isSuperadmin && !empty($r['company_name'])): ?>· <?= h((string) $r['company_name']) ?><?php endif; ?>
            </div>
            <?php if (!empty($r['text'])): ?>
                <div class="mb-1"><?= nl2br(h((string) $r['text'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['suggestions'])): ?>
                <div class="small text-muted mb-1">Предложения: <?= nl2br(h((string) $r['suggestions'])) ?></div>
            <?php endif; ?>

            <form method="post" class="mt-2">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="action" value="<?= (int) $r['is_hidden'] === 1 ? 'unhide' : 'hide' ?>">
                <button class="btn btn-sm btn-outline-secondary"><?= (int) $r['is_hidden'] === 1 ? 'Показать' : 'Скрыть' ?></button>
            </form>
        </div>
    <?php endforeach; ?>

</div>
</body>
</html>
