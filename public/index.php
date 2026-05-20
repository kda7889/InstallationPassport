<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$topCompanies = db()->query(
    "SELECT c.id, c.name, c.logo_path,
            COUNT(r.id) AS reviews_count,
            ROUND(AVG(r.overall_rating), 1) AS avg_rating
     FROM companies c
     JOIN installations i ON i.company_id = c.id
     JOIN reviews r ON r.installation_id = i.id AND r.is_hidden = 0 AND r.overall_rating IS NOT NULL
     WHERE c.is_active = 1
     GROUP BY c.id
     HAVING reviews_count >= 1
     ORDER BY avg_rating DESC, reviews_count DESC
     LIMIT 10"
)->fetchAll();

$topInstallers = db()->query(
    "SELECT u.id, u.name, u.company_id, c.name AS company_name,
            COUNT(r.id) AS reviews_count,
            ROUND(AVG(r.overall_rating), 1) AS avg_rating
     FROM users u
     JOIN installations i ON i.user_id = u.id
     JOIN reviews r ON r.installation_id = i.id AND r.is_hidden = 0 AND r.overall_rating IS NOT NULL
     JOIN companies c ON c.id = u.company_id AND c.is_active = 1
     WHERE u.is_active = 1 AND u.role = 'installer'
     GROUP BY u.id
     HAVING reviews_count >= 1
     ORDER BY avg_rating DESC, reviews_count DESC
     LIMIT 10"
)->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>МонтажПаспорт — прозрачный гарантийник на каждый монтаж</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .hero { background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%); color: #fff; padding: 60px 0; }
        .star { color: #ffc107; }
        .star.empty { color: #ddd; }
        .feature-card { height: 100%; }
    </style>
</head>
<body class="bg-light">

<section class="hero">
    <div class="container">
        <h1 class="display-5 fw-bold mb-3">МонтажПаспорт</h1>
        <p class="lead mb-4">Прозрачный гарантийный талон на каждый монтаж. С фотоотчётом, QR-кодом подлинности и онлайн-отзывами клиентов.</p>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/login.php" class="btn btn-light btn-lg">Войти (для монтажников и админов)</a>
            <a href="#find" class="btn btn-outline-light btn-lg">Я заказчик — найти талон</a>
        </div>
    </div>
</section>

<div class="container py-5">

    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="card feature-card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Прозрачно для заказчика</h2>
                    <p class="small text-muted">Каждый клиент получает PDF-талон с QR-кодом. Отсканировал — увидел фото, гарантию, подлинность. Личные данные скрыты по 152-ФЗ.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Удобно монтажнику</h2>
                    <p class="small text-muted">Все фото — с телефона прямо на сайт. Подсказки «что обычно стоит снять». PDF готов одной кнопкой.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Честно для компании</h2>
                    <p class="small text-muted">Заказчики оставляют отзывы по коду из PDF. Подделать невозможно. Рейтинг растёт с реальной работой.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-body shadow-sm mb-5" id="find">
        <h2 class="h4 mb-3">Я заказчик — найти свой талон</h2>
        <p class="text-muted small">Возьмите номер и код доступа с вашего PDF-гарантийника.</p>
        <form method="get" action="/customer.php" class="row g-2">
            <div class="col-12 col-md-6">
                <input class="form-control form-control-lg" name="n" placeholder="Номер: MP-20260519-A3F4E2" required>
            </div>
            <div class="col-12 col-md-4">
                <input class="form-control form-control-lg" name="c" placeholder="Код доступа" required>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button class="btn btn-primary btn-lg">Открыть</button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <h2 class="h5 mb-3">🏆 Топ компаний</h2>
            <?php if (!$topCompanies): ?>
                <div class="text-muted small">Рейтинг появится, когда клиенты начнут оставлять отзывы.</div>
            <?php else: ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($topCompanies as $i => $c): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><?= ($i + 1) ?>. <?= h((string) $c['name']) ?></div>
                                <div class="small text-muted">
                                    <?php for ($j = 1; $j <= 5; $j++): ?>
                                        <span class="star <?= $j <= (int) round((float) $c['avg_rating']) ? '' : 'empty' ?>">★</span>
                                    <?php endfor; ?>
                                    <?= h((string) $c['avg_rating']) ?> · <?= (int) $c['reviews_count'] ?> отзывов
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <h2 class="h5 mb-3">⭐ Топ монтажников</h2>
            <?php if (!$topInstallers): ?>
                <div class="text-muted small">Рейтинг появится, когда клиенты начнут оставлять отзывы.</div>
            <?php else: ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($topInstallers as $i => $u): ?>
                        <div class="list-group-item">
                            <div class="fw-semibold"><?= ($i + 1) ?>. <?= h((string) $u['name']) ?></div>
                            <div class="small text-muted">
                                <?php for ($j = 1; $j <= 5; $j++): ?>
                                    <span class="star <?= $j <= (int) round((float) $u['avg_rating']) ? '' : 'empty' ?>">★</span>
                                <?php endfor; ?>
                                <?= h((string) $u['avg_rating']) ?> · <?= (int) $u['reviews_count'] ?> отзывов
                                <?php if (!empty($u['company_name'])): ?>· <?= h((string) $u['company_name']) ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-center text-muted small">
        Сервис только что запущен. Если у вас компания — <a href="/login.php">войдите</a> или напишите нам, чтобы подключиться.
    </div>

</div>
</body>
</html>
