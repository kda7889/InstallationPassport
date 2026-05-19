<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

$error = null;
$ok = ($_GET['ok'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate(post('_csrf'))) {
    $current = (string) post('current_password', '');
    $new = (string) post('new_password', '');
    $confirm = (string) post('confirm_password', '');

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Текущий пароль введён неверно.';
    } elseif (mb_strlen($new) < 8) {
        $error = 'Новый пароль должен быть не короче 8 символов.';
    } elseif ($new !== $confirm) {
        $error = 'Новый пароль и подтверждение не совпадают.';
    } elseif ($new === $current) {
        $error = 'Новый пароль совпадает с текущим.';
    } else {
        db()->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated_at WHERE id = :id')
            ->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'updated_at' => now(), 'id' => $user['id']]);
        audit_log('user.self_password_changed', 'user', (int) $user['id']);
        redirect('/profile.php?ok=1');
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Профиль — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">

    <a href="/dashboard.php" class="btn btn-link px-0">← К монтажам</a>
    <h1 class="h4 mb-3">Мой профиль</h1>

    <div class="card card-body mb-3 shadow-sm">
        <div><span class="text-muted">Имя:</span> <?= h((string) $user['name']) ?></div>
        <div><span class="text-muted">Email:</span> <?= h((string) $user['email']) ?></div>
        <div><span class="text-muted">Роль:</span> <?= h((string) $user['role']) ?></div>
    </div>

    <h2 class="h5 mb-2">Смена пароля</h2>

    <?php if ($ok): ?>
        <div class="alert alert-success">Пароль обновлён.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card card-body shadow-sm">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <label class="form-label">Текущий пароль</label>
        <input class="form-control mb-2" type="password" name="current_password" required autocomplete="current-password">

        <label class="form-label">Новый пароль (минимум 8 символов)</label>
        <input class="form-control mb-2" type="password" name="new_password" minlength="8" required autocomplete="new-password">

        <label class="form-label">Подтверждение нового пароля</label>
        <input class="form-control mb-3" type="password" name="confirm_password" minlength="8" required autocomplete="new-password">

        <button class="btn btn-primary" type="submit">Сменить пароль</button>
    </form>

</div>
</body>
</html>
