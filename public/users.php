<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
$user = current_user();

$isSuperadmin = !empty($user['is_superadmin']);
$companyId = (int) ($user['company_id'] ?? 0);
$companyFilter = $isSuperadmin ? (int) ($_GET['company_id'] ?? 0) : $companyId;

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate(post('_csrf'))) {
        $error = 'Ошибка CSRF.';
    } else {
        $action = (string) post('action', 'create');

        if ($action === 'create') {
            $name = trim((string) post('name', ''));
            $email = mb_strtolower(trim((string) post('email', '')));
            $phone = trim((string) post('phone', ''));
            $role = (string) post('role', 'installer');
            $password = (string) post('password', '');
            $targetCompany = $isSuperadmin ? (int) post('company_id', (string) $companyId) : $companyId;

            if ($name === '' || $email === '' || $password === '' || !in_array($role, ['admin', 'installer'], true)) {
                $error = 'Заполните обязательные поля.';
            } elseif ($targetCompany <= 0) {
                $error = 'Выберите компанию.';
            } else {
                try {
                    db()->prepare('INSERT INTO users (name, phone, email, password_hash, role, company_id, is_active, created_at, updated_at) VALUES (:name,:phone,:email,:password_hash,:role,:company_id,1,:created_at,:updated_at)')->execute([
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                        'company_id' => $targetCompany,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    audit_log('user.created', 'user', (int) db()->lastInsertId(), ['email' => $email, 'role' => $role, 'company_id' => $targetCompany]);
                    redirect('/users.php' . ($isSuperadmin && $companyFilter ? '?company_id=' . $companyFilter : ''));
                } catch (Throwable $e) {
                    $error = 'Пользователь с таким email уже существует.';
                }
            }
        }

        if ($action === 'toggle') {
            $targetId = (int) post('target_id', '0');
            $tStmt = db()->prepare('SELECT role, is_active, company_id, is_superadmin FROM users WHERE id = :id');
            $tStmt->execute(['id' => $targetId]);
            $target = $tStmt->fetch();

            if ($targetId === (int) $user['id']) {
                $error = 'Нельзя деактивировать самого себя.';
            } elseif (!$target) {
                $error = 'Пользователь не найден.';
            } elseif (!$isSuperadmin && (int) $target['company_id'] !== $companyId) {
                $error = 'Этот пользователь не из вашей компании.';
            } elseif ($target['role'] === 'admin' && (int) $target['is_active'] === 1) {
                $cnt = (int) db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND company_id = :cid")->execute(['cid' => $target['company_id']]);
                $cnt = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND company_id = " . (int) $target['company_id'])->fetchColumn();
                if ($cnt <= 1) {
                    $error = 'Нельзя деактивировать последнего активного администратора компании.';
                }
            }
            if ($error === null) {
                db()->prepare('UPDATE users SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=:updated_at WHERE id=:id')->execute([
                    'updated_at' => now(),
                    'id' => $targetId,
                ]);
                audit_log('user.toggled', 'user', $targetId);
                redirect('/users.php' . ($isSuperadmin && $companyFilter ? '?company_id=' . $companyFilter : ''));
            }
        }

        if ($action === 'set_password') {
            $targetId = (int) post('target_id', '0');
            $newPassword = (string) post('new_password', '');
            $tStmt = db()->prepare('SELECT company_id FROM users WHERE id = :id');
            $tStmt->execute(['id' => $targetId]);
            $target = $tStmt->fetch();

            if (!$target) {
                $error = 'Пользователь не найден.';
            } elseif (!$isSuperadmin && (int) $target['company_id'] !== $companyId) {
                $error = 'Этот пользователь не из вашей компании.';
            } elseif (mb_strlen($newPassword) < 8) {
                $error = 'Пароль должен быть не менее 8 символов.';
            } else {
                db()->prepare('UPDATE users SET password_hash=:password_hash, updated_at=:updated_at WHERE id=:id')->execute([
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'updated_at' => now(),
                    'id' => $targetId,
                ]);
                audit_log('user.password_changed', 'user', $targetId);
                redirect('/users.php' . ($isSuperadmin && $companyFilter ? '?company_id=' . $companyFilter : ''));
            }
        }
    }
}

$where = '';
$params = [];
if (!$isSuperadmin) {
    $where = 'WHERE company_id = :cid';
    $params['cid'] = $companyId;
} elseif ($companyFilter > 0) {
    $where = 'WHERE company_id = :cid';
    $params['cid'] = $companyFilter;
}
$stmt = db()->prepare("SELECT u.*, c.name AS company_name FROM users u LEFT JOIN companies c ON c.id = u.company_id $where ORDER BY u.id DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$allCompanies = $isSuperadmin ? db()->query('SELECT id, name FROM companies ORDER BY name')->fetchAll() : [];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Пользователи — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">
    <a href="/dashboard.php" class="btn btn-link px-0">← К монтажам</a>
    <h1 class="h4">Пользователи</h1>

    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <?php if ($isSuperadmin && $allCompanies): ?>
        <form method="get" class="card card-body mb-3 shadow-sm">
            <label class="form-label small text-muted">Фильтр по компании</label>
            <select name="company_id" class="form-select" onchange="this.form.submit()">
                <option value="0">Все компании</option>
                <?php foreach ($allCompanies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $companyFilter === (int) $c['id'] ? 'selected' : '' ?>><?= h((string) $c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <form method="post" class="card card-body mb-3 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <h2 class="h6 text-muted mb-2">Добавить пользователя</h2>
        <div class="row g-2">
            <?php if ($isSuperadmin): ?>
                <div class="col-12">
                    <select name="company_id" class="form-select" required>
                        <option value="">Выберите компанию</option>
                        <?php foreach ($allCompanies as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $companyFilter === (int) $c['id'] ? 'selected' : '' ?>><?= h((string) $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-12"><input class="form-control" name="name" placeholder="Имя" required></div>
            <div class="col-12"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
            <div class="col-12"><input class="form-control" name="phone" placeholder="Телефон"></div>
            <div class="col-12"><select class="form-select" name="role"><option value="installer">Монтажник</option><option value="admin">Администратор</option></select></div>
            <div class="col-12"><input class="form-control" name="password" type="password" minlength="8" placeholder="Пароль (минимум 8)" required></div>
            <div class="col-12"><button class="btn btn-primary w-100">Создать</button></div>
        </div>
    </form>

    <div class="list-group shadow-sm">
        <?php foreach ($users as $u): ?>
            <div class="list-group-item">
                <div class="fw-semibold">
                    <?= h((string) $u['name']) ?>
                    <span class="badge bg-light text-dark border ms-1"><?= h((string) $u['role']) ?></span>
                    <?php if ((int) ($u['is_superadmin'] ?? 0) === 1): ?>
                        <span class="badge bg-warning text-dark ms-1">superadmin</span>
                    <?php endif; ?>
                </div>
                <div class="small text-muted">
                    <?= h((string) $u['email']) ?> ·
                    <?= (int) $u['is_active'] === 1 ? 'Активен' : 'Отключен' ?>
                    <?php if ($isSuperadmin && !empty($u['company_name'])): ?>
                        · <?= h((string) $u['company_name']) ?>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary"><?= (int) $u['is_active'] === 1 ? 'Деактивировать' : 'Активировать' ?></button>
                    </form>
                    <form method="post" class="d-flex gap-2">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="set_password">
                        <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
                        <input class="form-control form-control-sm" type="password" name="new_password" minlength="8" placeholder="Новый пароль" required>
                        <button class="btn btn-sm btn-outline-primary">Сменить</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
</body>
</html>
