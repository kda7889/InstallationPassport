<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Доступ запрещён');
}

$actionFilter = trim((string) ($_GET['action'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($actionFilter !== '') {
    $where = 'WHERE al.action LIKE :pattern';
    $params['pattern'] = $actionFilter . '%';
}

$sql = "SELECT al.*, u.email AS user_email
        FROM audit_log al
        LEFT JOIN users u ON u.id = al.user_id
        $where
        ORDER BY al.id DESC
        LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$actionsStmt = db()->query("SELECT DISTINCT substr(action, 1, instr(action || '.', '.') - 1) AS prefix FROM audit_log ORDER BY prefix");
$actionPrefixes = array_filter(array_column($actionsStmt->fetchAll(), 'prefix'));

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Журнал событий — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">

    <a href="/dashboard.php" class="btn btn-link px-0">← К монтажам</a>
    <h1 class="h4 mb-3">Журнал событий</h1>

    <form method="get" class="card card-body mb-3 shadow-sm">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
                <label class="form-label">Фильтр по типу действия</label>
                <select name="action" class="form-select">
                    <option value="">Все события</option>
                    <?php foreach ($actionPrefixes as $prefix): ?>
                        <option value="<?= h((string) $prefix) ?>" <?= $actionFilter === $prefix ? 'selected' : '' ?>><?= h((string) $prefix) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <button class="btn btn-primary w-100" type="submit">Применить</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-striped bg-white shadow-sm">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Пользователь</th>
                    <th>IP</th>
                    <th>Действие</th>
                    <th>Объект</th>
                    <th>Доп. данные</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="small text-nowrap"><?= h((string) $row['created_at']) ?></td>
                    <td class="small"><?= h((string) ($row['user_email'] ?? '—')) ?></td>
                    <td class="small text-muted"><?= h((string) ($row['ip'] ?? '')) ?></td>
                    <td class="small"><code><?= h((string) $row['action']) ?></code></td>
                    <td class="small">
                        <?php if (!empty($row['entity_type'])): ?>
                            <?= h((string) $row['entity_type']) ?>#<?= (int) $row['entity_id'] ?>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted text-truncate" style="max-width:300px;" title="<?= h((string) ($row['metadata'] ?? '')) ?>">
                        <?= h((string) ($row['metadata'] ?? '')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted">Событий нет.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <nav class="mt-3">
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?action=<?= urlencode($actionFilter) ?>&page=<?= $page - 1 ?>">←</a></li>
            <?php endif; ?>
            <li class="page-item disabled"><span class="page-link">Страница <?= $page ?></span></li>
            <?php if (count($rows) === $perPage): ?>
                <li class="page-item"><a class="page-link" href="?action=<?= urlencode($actionFilter) ?>&page=<?= $page + 1 ?>">→</a></li>
            <?php endif; ?>
        </ul>
    </nav>

</div>
</body>
</html>
