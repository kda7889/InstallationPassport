<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

$isSuperadmin = !empty($user['is_superadmin']);
$isAdmin = ($user['role'] ?? '') === 'admin' || $isSuperadmin;

$sql = 'SELECT i.*, w.name as work_type_name, u.name AS owner_name, u.email AS owner_email, c.name AS company_name FROM installations i JOIN work_types w ON w.id = i.work_type_id LEFT JOIN users u ON u.id = i.user_id LEFT JOIN companies c ON c.id = i.company_id';
$params = [];
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$typeFilter = (int) ($_GET['work_type_id'] ?? 0);
$userFilter = $isAdmin ? (int) ($_GET['user_id'] ?? 0) : 0;
$companyFilter = $isSuperadmin ? (int) ($_GET['company_id'] ?? 0) : (int) ($user['company_id'] ?? 0);
$query = trim((string) ($_GET['q'] ?? ''));
$where = [];

if (!$isSuperadmin) {
    $where[] = 'i.company_id = :scope_company';
    $params['scope_company'] = (int) ($user['company_id'] ?? 0);
} elseif ($companyFilter > 0) {
    $where[] = 'i.company_id = :scope_company';
    $params['scope_company'] = $companyFilter;
}
if (!$isAdmin) {
    $where[] = 'i.user_id = :user_id';
    $params['user_id'] = $user['id'];
} elseif ($userFilter > 0) {
    $where[] = 'i.user_id = :user_id';
    $params['user_id'] = $userFilter;
}
if ($statusFilter !== '') {
    $where[] = 'i.status = :status';
    $params['status'] = $statusFilter;
}
if ($typeFilter > 0) {
    $where[] = 'i.work_type_id = :work_type_id';
    $params['work_type_id'] = $typeFilter;
}
if ($query !== '') {
    $where[] = '(i.address LIKE :q OR i.customer_name LIKE :q OR i.number LIKE :q)';
    $params['q'] = '%' . $query . '%';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY i.created_at DESC LIMIT 100';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$installations = $stmt->fetchAll();
$types = db()->query('SELECT id, name FROM work_types WHERE is_active=1 ORDER BY sort_order')->fetchAll();

if ($isSuperadmin) {
    $users = db()->query('SELECT id, name, email FROM users ORDER BY name')->fetchAll();
    $companies = db()->query('SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name')->fetchAll();
} elseif ($isAdmin) {
    $stmt = db()->prepare('SELECT id, name, email FROM users WHERE company_id = :cid ORDER BY name');
    $stmt->execute(['cid' => (int) ($user['company_id'] ?? 0)]);
    $users = $stmt->fetchAll();
    $companies = [];
} else {
    $users = [];
    $companies = [];
}

$statusLabels = [
    'draft' => 'Черновик',
    'in_progress' => 'В работе',
    'photos_partial' => 'Фото частично',
    'ready' => 'Готов к PDF',
    'pdf_generated' => 'PDF сформирован',
    'closed' => 'Закрыт',
];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Монтажи — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Монтажи</h1>
        <div class="text-end small">
            <a href="/profile.php" class="text-decoration-none"><?= h((string) ($user['email'] ?? '')) ?></a>
            · <a href="/logout.php">Выход</a>
        </div>
    </div>

    <a class="btn btn-primary btn-lg w-100 mb-2" href="/installation_create.php">+ Новый монтаж</a>

    <?php if ($isAdmin): ?>
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <a class="btn btn-outline-dark flex-grow-1" href="/users.php">Пользователи</a>
            <a class="btn btn-outline-dark flex-grow-1" href="/company_edit.php">Моя компания</a>
            <a class="btn btn-outline-dark flex-grow-1" href="/reviews.php">Отзывы</a>
            <a class="btn btn-outline-dark flex-grow-1" href="/audit.php">Журнал</a>
            <?php if ($isSuperadmin): ?>
                <a class="btn btn-outline-warning flex-grow-1" href="/companies.php">Компании</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="get" class="card card-body mb-3 shadow-sm">
        <div class="row g-2">
            <div class="col-12">
                <input name="q" class="form-control" value="<?= h($query) ?>" placeholder="Поиск по адресу, заказчику или номеру">
            </div>
            <div class="col-6">
                <select name="work_type_id" class="form-select">
                    <option value="0">Все типы</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int) $type['id'] ?>" <?= $typeFilter === (int) $type['id'] ? 'selected' : '' ?>><?= h((string) $type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <select name="status" class="form-select">
                    <option value="">Все статусы</option>
                    <?php foreach ($statusLabels as $code => $label): ?>
                        <option value="<?= h($code) ?>" <?= $statusFilter === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($isAdmin && $users): ?>
                <div class="col-12">
                    <select name="user_id" class="form-select">
                        <option value="0">Все исполнители</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= $userFilter === (int) $u['id'] ? 'selected' : '' ?>><?= h((string) ($u['name'] ?: $u['email'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($isSuperadmin && $companies): ?>
                <div class="col-12">
                    <select name="company_id" class="form-select">
                        <option value="0">Все компании</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $companyFilter === (int) $c['id'] ? 'selected' : '' ?>><?= h((string) $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12">
                <button class="btn btn-outline-primary w-100">Применить</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="list-group list-group-flush">
            <?php foreach ($installations as $i): ?>
                <a href="/installation_edit.php?id=<?= (int) $i['id'] ?>" class="list-group-item list-group-item-action">
                    <div class="fw-semibold"><?= h((string) $i['number']) ?> · <?= h((string) $i['work_type_name']) ?></div>
                    <div class="small text-muted"><?= h((string) $i['address']) ?></div>
                    <div class="small">
                        <span class="badge bg-light text-dark border"><?= h((string) ($statusLabels[$i['status']] ?? $i['status'])) ?></span>
                        <?php if ($isSuperadmin && !empty($i['company_name'])): ?>
                            <span class="badge bg-warning text-dark ms-1"><?= h((string) $i['company_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($isAdmin && !empty($i['owner_email'])): ?>
                            <span class="text-muted ms-2">— <?= h((string) ($i['owner_name'] ?: $i['owner_email'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($i['customer_name'])): ?>
                            <span class="text-muted ms-2">· заказчик: <?= h((string) $i['customer_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
            <?php if (!$installations): ?>
                <div class="list-group-item text-muted">Монтажи не найдены.</div>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>
