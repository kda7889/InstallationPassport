<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$number = mb_strtoupper(trim((string) ($_GET['n'] ?? $_POST['n'] ?? '')));
$code = strtolower(trim((string) ($_GET['c'] ?? $_POST['c'] ?? '')));

if ($number === '' || $code === '') {
    http_response_code(400);
    exit('Bad request');
}

$stmt = db()->prepare('SELECT * FROM installations WHERE number = :number LIMIT 1');
$stmt->execute(['number' => $number]);
$installation = $stmt->fetch();

if (!$installation || !is_string($installation['access_token']) || !hash_equals((string) $installation['access_token'], $code)) {
    http_response_code(403);
    exit('Доступ запрещён. Используйте личный код доступа из PDF.');
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

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $period = (string) post('period_label', 'initial');
    if (!isset($periodLabels[$period])) {
        $period = 'custom';
    }
    $text = trim((string) post('text', ''));
    $suggestions = trim((string) post('suggestions', ''));
    $name = trim((string) post('name', ''));

    $ratings = [];
    foreach ($criteria as $key => $_label) {
        $stars = (int) post('rating_' . $key, '0');
        if ($stars >= 1 && $stars <= 5) {
            $ratings[$key] = $stars;
        }
    }

    if (!$ratings && $text === '') {
        $error = 'Оцените хотя бы один критерий или напишите отзыв.';
    } else {
        $overall = $ratings ? (int) round(array_sum($ratings) / count($ratings)) : null;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO reviews (installation_id, period_label, overall_rating, text, suggestions, customer_name_provided, is_hidden, created_at) VALUES (:iid, :period, :overall, :text, :suggestions, :name, 0, :created_at)')
                ->execute([
                    'iid' => $installation['id'],
                    'period' => $period,
                    'overall' => $overall,
                    'text' => $text,
                    'suggestions' => $suggestions,
                    'name' => $name !== '' ? $name : null,
                    'created_at' => now(),
                ]);
            $reviewId = (int) $pdo->lastInsertId();
            $rIns = $pdo->prepare('INSERT INTO review_ratings (review_id, criterion, stars) VALUES (:review_id, :criterion, :stars)');
            foreach ($ratings as $key => $stars) {
                $rIns->execute(['review_id' => $reviewId, 'criterion' => $key, 'stars' => $stars]);
            }
            $pdo->commit();
            audit_log('review.submitted', 'review', $reviewId, ['installation_id' => $installation['id'], 'period' => $period]);
            redirect('/customer.php?n=' . urlencode($number) . '&c=' . urlencode($code));
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

$branding = company_branding((int) $installation['company_id']);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оставить отзыв — <?= h((string) $installation['number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .star-row { display: flex; gap: 4px; font-size: 28px; }
        .star-row input { display: none; }
        .star-row label { cursor: pointer; color: #ddd; }
        .star-row input:checked ~ label { color: #ddd; }
        .star-row > input:checked + label,
        .star-row > input:checked + label ~ label { color: #ffc107; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">

    <a href="/customer.php?n=<?= urlencode($number) ?>&c=<?= urlencode($code) ?>" class="btn btn-link px-0">← К талону</a>
    <h1 class="h4 mb-3">Оставить отзыв</h1>

    <?php if (!empty($branding['name'])): ?>
        <p class="text-muted">Компания: <strong><?= h((string) $branding['name']) ?></strong> · Талон: <?= h((string) $installation['number']) ?></p>
    <?php endif; ?>

    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <form method="post" class="card card-body shadow-sm">
        <input type="hidden" name="n" value="<?= h($number) ?>">
        <input type="hidden" name="c" value="<?= h($code) ?>">

        <label class="form-label">Когда монтаж? Дайте контексту:</label>
        <select name="period_label" class="form-select mb-3" required>
            <?php foreach ($periodLabels as $k => $label): ?>
                <option value="<?= h($k) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>

        <h2 class="h6 text-muted mb-2">Оцените по критериям</h2>
        <?php foreach ($criteria as $key => $label): ?>
            <div class="mb-3">
                <div class="small mb-1"><?= h($label) ?></div>
                <div class="star-row">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating_<?= h($key) ?>" id="rating_<?= h($key) ?>_<?= $i ?>" value="<?= $i ?>">
                        <label for="rating_<?= h($key) ?>_<?= $i ?>">★</label>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <label class="form-label">Что понравилось</label>
        <textarea class="form-control mb-2" rows="3" name="text" placeholder="Например: приехали вовремя, оборудование работает тихо."></textarea>

        <label class="form-label">Что улучшить или критика</label>
        <textarea class="form-control mb-2" rows="2" name="suggestions" placeholder="Конструктивные предложения помогут компании стать лучше."></textarea>

        <label class="form-label">Как вас подписать (необязательно)</label>
        <input class="form-control mb-3" name="name" placeholder="например, Иван П.">

        <p class="small text-muted">Имя и текст будут видны всем, кто откроет публичную проверку талона. Никакие ваши контактные данные не публикуются.</p>

        <button class="btn btn-primary" type="submit">Отправить отзыв</button>
    </form>

</div>
</body>
</html>
