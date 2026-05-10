<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

$sql = 'SELECT i.*, w.name as work_type_name FROM installations i JOIN work_types w ON w.id = i.work_type_id';
$params = [];
if (($user['role'] ?? '') !== 'admin') {
    $sql .= ' WHERE i.user_id = :user_id';
    $params['user_id'] = $user['id'];
}
$sql .= ' ORDER BY i.created_at DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$installations = $stmt->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Монтажи</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Монтажи</h1>
        <a class="btn btn-outline-secondary" href="/logout.php">Выход</a>
    </div>
    <a class="btn btn-primary btn-lg w-100 mb-3" href="/installation_create.php">+ Новый монтаж</a>
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
