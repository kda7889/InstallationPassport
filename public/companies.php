<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_superadmin();
$user = current_user();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate(post('_csrf'))) {
    $action = (string) post('action', 'create');

    if ($action === 'create') {
        $name = trim((string) post('name', ''));
        $inn = trim((string) post('inn', ''));
        $phone = trim((string) post('phone', ''));
        $email = trim((string) post('email', ''));
        $address = trim((string) post('address', ''));
        if ($name === '') {
            $error = 'Название обязательно.';
        } else {
            $slug = mb_strtolower(preg_replace('/[^a-z0-9-]+/i', '-', trim($name . '-' . bin2hex(random_bytes(2)), '-')) ?: 'company-' . bin2hex(random_bytes(3)));
            db()->prepare('INSERT INTO companies (name, slug, inn, phone, email, address, is_active) VALUES (:name, :slug, :inn, :phone, :email, :address, 1)')
                ->execute(['name' => $name, 'slug' => $slug, 'inn' => $inn, 'phone' => $phone, 'email' => $email, 'address' => $address]);
            audit_log('company.created', 'company', (int) db()->lastInsertId(), ['name' => $name]);
            redirect('/companies.php');
        }
    }

    if ($action === 'toggle') {
        $targetId = (int) post('id', '0');
        db()->prepare('UPDATE companies SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id')
            ->execute(['id' => $targetId]);
        audit_log('company.toggled', 'company', $targetId);
        redirect('/companies.php');
    }
}

$companies = db()->query('SELECT c.*, (SELECT COUNT(*) FROM users WHERE company_id = c.id) AS user_count, (SELECT COUNT(*) FROM installations WHERE company_id = c.id) AS installation_count FROM companies c ORDER BY c.is_active DESC, c.name')->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Компании — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">

    <a href="/dashboard.php" class="btn btn-link px-0">← К монтажам</a>
    <h1 class="h4 mb-3">Компании</h1>

    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <form method="post" class="card card-body mb-3 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <h2 class="h6 text-muted mb-2">Добавить компанию</h2>
        <div class="row g-2">
            <div class="col-12"><input class="form-control" name="name" placeholder="Название" required></div>
            <div class="col-6 col-md-3"><input class="form-control" name="inn" placeholder="ИНН"></div>
            <div class="col-6 col-md-3"><input class="form-control" name="phone" placeholder="Телефон"></div>
            <div class="col-12 col-md-6"><input class="form-control" name="email" type="email" placeholder="Email"></div>
            <div class="col-12"><input class="form-control" name="address" placeholder="Адрес"></div>
            <div class="col-12"><button class="btn btn-primary w-100">Создать</button></div>
        </div>
    </form>

    <div class="list-group shadow-sm">
        <?php foreach ($companies as $c): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold">
                            <?= h((string) $c['name']) ?>
                            <?php if ((int) $c['is_active'] !== 1): ?>
                                <span class="badge bg-secondary ms-1">отключена</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">
                            <?php if (!empty($c['inn'])): ?>ИНН <?= h((string) $c['inn']) ?> · <?php endif; ?>
                            пользователей: <?= (int) $c['user_count'] ?> · монтажей: <?= (int) $c['installation_count'] ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="post">
                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary"><?= (int) $c['is_active'] === 1 ? 'Отключить' : 'Включить' ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>
