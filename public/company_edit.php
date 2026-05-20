<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
$user = current_user();

$companyId = (int) ($user['company_id'] ?? 0);
if (!empty($user['is_superadmin']) && isset($_GET['id'])) {
    $companyId = (int) $_GET['id'];
}
$company = $companyId > 0 ? company($companyId) : null;
if (!$company) {
    http_response_code(404);
    exit('Компания не найдена');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate(post('_csrf'))) {
    $fields = [
        'name' => trim((string) post('name', '')),
        'inn' => trim((string) post('inn', '')),
        'phone' => trim((string) post('phone', '')),
        'email' => trim((string) post('email', '')),
        'address' => trim((string) post('address', '')),
    ];
    if ($fields['name'] === '') {
        $error = 'Название обязательно.';
    }

    if ($error === null && !empty($_FILES['logo']['tmp_name']) && (int) $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        if (!is_uploaded_file((string) $_FILES['logo']['tmp_name'])) {
            $error = 'Некорректная загрузка файла.';
        } else {
            $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $_FILES['logo']['tmp_name']);
            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                $error = 'Логотип должен быть PNG, JPG или WebP.';
            } elseif ((int) $_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $error = 'Логотип больше 2 МБ.';
            } else {
                $brandingDir = dirname(__DIR__) . '/storage/branding';
                if (!is_dir($brandingDir) && !@mkdir($brandingDir, 0775, true) && !is_dir($brandingDir)) {
                    $error = 'Не удалось создать папку.';
                } else {
                    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                    $dest = $brandingDir . '/company-' . $companyId . '.' . $ext;
                    foreach (glob($brandingDir . '/company-' . $companyId . '.*') ?: [] as $old) {
                        @unlink($old);
                    }
                    if (!move_uploaded_file((string) $_FILES['logo']['tmp_name'], $dest)) {
                        $error = 'Не удалось сохранить логотип.';
                    } else {
                        $fields['logo_path'] = 'storage/branding/company-' . $companyId . '.' . $ext;
                    }
                }
            }
        }
    }

    if ($error === null && post('remove_logo') === '1') {
        $currentLogo = (string) ($company['logo_path'] ?? '');
        if ($currentLogo !== '') {
            @unlink(dirname(__DIR__) . '/' . $currentLogo);
            $fields['logo_path'] = '';
        }
    }

    if ($error === null) {
        update_company($companyId, $fields);
        audit_log('company.updated', 'company', $companyId);
        redirect('/company_edit.php?id=' . $companyId . '&ok=1');
    }
}

$company = company($companyId);
$showOk = ($_GET['ok'] ?? '') === '1';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h((string) $company['name']) ?> — Настройки компании</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">

    <a href="<?= !empty($user['is_superadmin']) && isset($_GET['id']) ? '/companies.php' : '/dashboard.php' ?>" class="btn btn-link px-0">← Назад</a>
    <h1 class="h4 mb-3">Настройки компании</h1>

    <?php if ($showOk): ?><div class="alert alert-success">Сохранено.</div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <p class="text-muted small">Печатается в шапке PDF и на онлайн-странице для заказчика.</p>

    <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <label class="form-label">Название компании</label>
        <input class="form-control mb-2" name="name" value="<?= h((string) $company['name']) ?>" required>

        <label class="form-label">ИНН / ОГРН</label>
        <input class="form-control mb-2" name="inn" value="<?= h((string) ($company['inn'] ?? '')) ?>">

        <label class="form-label">Телефон</label>
        <input class="form-control mb-2" type="tel" name="phone" value="<?= h((string) ($company['phone'] ?? '')) ?>">

        <label class="form-label">Email</label>
        <input class="form-control mb-2" type="email" name="email" value="<?= h((string) ($company['email'] ?? '')) ?>">

        <label class="form-label">Юридический адрес</label>
        <textarea class="form-control mb-3" rows="2" name="address"><?= h((string) ($company['address'] ?? '')) ?></textarea>

        <label class="form-label">Логотип (PNG / JPG / WebP, до 2 МБ)</label>
        <?php if (!empty($company['logo_path'])): ?>
            <div class="mb-2">
                <img src="/branding_logo.php?company=<?= (int) $companyId ?>" alt="" style="max-height:120px; background:#fff; border:1px solid #ddd; padding:8px;">
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
