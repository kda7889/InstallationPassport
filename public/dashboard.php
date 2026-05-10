<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

$sql = 'SELECT i.*, w.name as work_type_name FROM installations i JOIN work_types w ON w.id = i.work_type_id';
$params = [];
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$typeFilter = (int) ($_GET['work_type_id'] ?? 0);
$where = [];
if (($user['role'] ?? '') !== 'admin') {
    $where[] = 'i.user_id = :user_id';
    $params['user_id'] = $user['id'];
}
if ($statusFilter !== '') {
    $where[] = 'i.status = :status';
    $params['status'] = $statusFilter;
}
if ($typeFilter > 0) {
    $where[] = 'i.work_type_id = :work_type_id';
    $params['work_type_id'] = $typeFilter;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY i.created_at DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$installations = $stmt->fetchAll();
$types = db()->query('SELECT id, name FROM work_types WHERE is_active=1 ORDER BY sort_order')->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Монтажи</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Монтажи</h1>
        <a class="btn btn-outline-secondary" href="/logout.php">Выход</a>
    </div>
    <a class="btn btn-primary btn-lg w-100 mb-3" href="/installation_create.php">+ Новый монтаж</a>
    <?php if (($user['role'] ?? '') === 'admin'): ?><a class="btn btn-outline-dark w-100 mb-3" href="/users.php">Пользователи</a><?php endif; ?>
    <form method="get" class="card card-body mb-3">
        <div class="row g-2">
            <div class="col-6"><select name="work_type_id" class="form-select"><option value="0">Все типы</option><?php foreach ($types as $type): ?><option value="<?= (int)$type['id'] ?>" <?= $typeFilter === (int)$type['id'] ? 'selected' : '' ?>><?= h((string)$type['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><select name="status" class="form-select"><option value="">Все статусы</option><?php foreach (['draft','in_progress','photos_partial','ready','pdf_generated','closed'] as $st): ?><option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><button class="btn btn-outline-primary w-100">Применить фильтр</button></div>
        </div>
    </form>
    <div class="card">
        <div class="list-group list-group-flush">
            <?php foreach ($installations as $i): ?>
                <a href="/installation_edit.php?id=<?= (int) $i['id'] ?>" class="list-group-item list-group-item-action">
                    <div class="fw-semibold"><?= h((string) $i['number']) ?> — <?= h((string) $i['work_type_name']) ?></div>
                    <div class="small text-muted"><?= h((string) $i['address']) ?> · <?= h((string) $i['status']) ?></div>
                </a>
            <?php endforeach; ?>
            <?php if (!$installations): ?><div class="list-group-item text-muted">Пока нет монтажей.</div><?php endif; ?>
        </div>
    </div>
</div>
</body></html>
