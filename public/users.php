<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Доступ запрещен');
}

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
            if ($name === '' || $email === '' || $password === '' || !in_array($role, ['admin', 'installer'], true)) {
                $error = 'Заполните обязательные поля.';
            } else {
                try {
                    db()->prepare('INSERT INTO users (name, phone, email, password_hash, role, is_active, created_at, updated_at) VALUES (:name,:phone,:email,:password_hash,:role,1,:created_at,:updated_at)')->execute([
                        'name' => $name,
                        'phone' => $phone,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $role,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    audit_log('user.created', 'user', (int) db()->lastInsertId(), ['email' => $email, 'role' => $role]);
                    redirect('/users.php');
                } catch (Throwable $e) {
                    $error = 'Пользователь с таким email уже существует.';
                }
            }
        }

        if ($action === 'toggle') {
            $targetId = (int) post('target_id', '0');
            if ($targetId === (int) $user['id']) {
                $error = 'Нельзя деактивировать самого себя.';
            } else {
                db()->prepare('UPDATE users SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END, updated_at=:updated_at WHERE id=:id')->execute([
                    'updated_at' => now(),
                    'id' => $targetId,
                ]);
                audit_log('user.toggled', 'user', $targetId);
                redirect('/users.php');
            }
        }

        if ($action === 'set_password') {
            $targetId = (int) post('target_id', '0');
            $newPassword = (string) post('new_password', '');
            if (mb_strlen($newPassword) < 6) {
                $error = 'Пароль должен быть не менее 6 символов.';
            } else {
                db()->prepare('UPDATE users SET password_hash=:password_hash, updated_at=:updated_at WHERE id=:id')->execute([
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'updated_at' => now(),
                    'id' => $targetId,
                ]);
                audit_log('user.password_changed', 'user', $targetId);
                redirect('/users.php');
            }
        }
    }
}

$users = db()->query('SELECT id, name, phone, email, role, is_active, created_at FROM users ORDER BY id DESC')->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Пользователи</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body>
<div class="container py-3">
    <a href="/dashboard.php" class="btn btn-link px-0">← К монтажам</a>
    <h1 class="h4">Пользователи</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <form method="post" class="card card-body mb-3">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="row g-2">
            <div class="col-12"><input class="form-control" name="name" placeholder="Имя" required></div>
            <div class="col-12"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
            <div class="col-12"><input class="form-control" name="phone" placeholder="Телефон"></div>
            <div class="col-12"><select class="form-select" name="role"><option value="installer">Монтажник</option><option value="admin">Администратор</option></select></div>
            <div class="col-12"><input class="form-control" name="password" type="password" minlength="6" placeholder="Пароль" required></div>
            <div class="col-12"><button class="btn btn-primary w-100">Создать пользователя</button></div>
        </div>
    </form>

    <div class="list-group">
    <?php foreach ($users as $u): ?>
        <div class="list-group-item">
            <div class="fw-semibold"><?= h((string) $u['name']) ?> (<?= h((string) $u['role']) ?>)</div>
            <div class="small text-muted"><?= h((string) $u['email']) ?> · <?= (int) $u['is_active'] === 1 ? 'Активен' : 'Отключен' ?></div>
            <div class="d-flex gap-2 mt-2">
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $u['is_active'] === 1 ? 'Деактивировать' : 'Активировать' ?></button>
                </form>
                <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="set_password">
                    <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
                    <input class="form-control form-control-sm" type="password" name="new_password" minlength="6" placeholder="Новый пароль" required>
                    <button class="btn btn-sm btn-outline-primary" type="submit">Сменить пароль</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
</body></html>
