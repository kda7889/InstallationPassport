<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_auth();
$user = current_user();

if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Доступ запрещён');
}

$error = null;
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate(post('_csrf'))) {
    set_setting('company_name', (string) post('company_name', ''));
    set_setting('company_inn', (string) post('company_inn', ''));
    set_setting('company_phone', (string) post('company_phone', ''));
    set_setting('company_address', (string) post('company_address', ''));
    set_setting('company_email', (string) post('company_email', ''));

    if (!empty($_FILES['logo']['tmp_name']) && (int) $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        if (!is_uploaded_file((string) $_FILES['logo']['tmp_name'])) {
            $error = 'Некорректная загрузка файла.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file((string) $_FILES['logo']['tmp_name']);
            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                $error = 'Логотип должен быть PNG, JPG или WebP.';
            } elseif ((int) $_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $error = 'Логотип больше 2 МБ.';
            } else {
                $brandingDir = dirname(__DIR__) . '/storage/branding';
                if (!is_dir($brandingDir) && !@mkdir($brandingDir, 0775, true) && !is_dir($brandingDir)) {
                    $error = 'Не удалось создать папку для логотипа.';
                } else {
                    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                    $dest = $brandingDir . '/logo.' . $ext;
                    foreach (glob($brandingDir . '/logo.*') ?: [] as $old) {
                        @unlink($old);
                    }
                    if (!move_uploaded_file((string) $_FILES['logo']['tmp_name'], $dest)) {
                        $error = 'Не удалось сохранить логотип на диск.';
                    } else {
                        set_setting('company_logo_path', 'storage/branding/logo.' . $ext);
                    }
                }
            }
        }
    }

    if (post('remove_logo') === '1') {
        $current = setting('company_logo_path');
        if ($current !== '') {
            @unlink(dirname(__DIR__) . '/' . $current);
            set_setting('company_logo_path', '');
        }
    }

    if ($error === null) {
        audit_log('settings.updated');
        $ok = true;
        redirect('/settings.php?ok=1');
    }
}

$branding = company_branding();
$showOk = ($_GET['ok'] ?? '') === '1';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Настройки — МонтажПаспорт</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">

    <a href="/dashboard.php" class="btn btn-link px-0">← К монтажам</a>
    <h1 class="h4 mb-3">Настройки компании</h1>

    <?php if ($showOk): ?>
        <div class="alert alert-success">Сохранено.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <p class="text-muted small">Эти данные печатаются в шапке гарантийного PDF и на онлайн-странице для заказчика.</p>

    <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <label class="form-label">Название компании</label>
        <input class="form-control mb-2" name="company_name" value="<?= h((string) $branding['name']) ?>" placeholder="ООО «МонтажПрофи»">

        <label class="form-label">ИНН / ОГРН</label>
        <input class="form-control mb-2" name="company_inn" value="<?= h((string) $branding['inn']) ?>">

        <label class="form-label">Телефон</label>
        <input class="form-control mb-2" type="tel" name="company_phone" value="<?= h((string) $branding['phone']) ?>">

        <label class="form-label">Email</label>
        <input class="form-control mb-2" type="email" name="company_email" value="<?= h((string) $branding['email']) ?>">

        <label class="form-label">Юридический адрес</label>
        <textarea class="form-control mb-3" rows="2" name="company_address"><?= h((string) $branding['address']) ?></textarea>

        <label class="form-label">Логотип (PNG / JPG / WebP, до 2 МБ)</label>
        <?php if (!empty($branding['logo_path'])): ?>
            <div class="mb-2">
                <img src="/branding_logo.php" alt="Текущий логотип" style="max-height:120px; background:#fff; border:1px solid #ddd; padding:8px;">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rmlogo">
                    <label class="form-check-label" for="rmlogo">Удалить текущий логотип</label>
                </div>
            </div>
        <?php endif; ?>
        <input class="form-control mb-3" type="file" name="logo" accept="image/png,image/jpeg,image/webp">

        <button class="btn btn-primary" type="submit">Сохранить</button>
    </form>

</div>
</body>
</html>
