<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate(post('_csrf'))) {
        $error = 'Ошибка CSRF. Обновите страницу.';
    } elseif (attempt_login((string) post('email', ''), (string) post('password', ''))) {
        redirect('/dashboard.php');
    } else {
        $error = 'Неверный логин или пароль.';
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-4">
            <h1 class="h3 mb-3">МонтажПаспорт</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
            <form method="post" class="card card-body shadow-sm">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control form-control-lg mb-3" required>
                <label class="form-label">Пароль</label>
                <input type="password" name="password" class="form-control form-control-lg mb-3" required>
                <button class="btn btn-primary btn-lg" type="submit">Войти</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
