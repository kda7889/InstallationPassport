<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    if (!is_dir(dirname($config['db_path']))) {
        mkdir(dirname($config['db_path']), 0775, true);
    }

    $pdo = new PDO('sqlite:' . $config['db_path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

function db_bootstrap_if_needed(): void
{
    $exists = db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    if ($exists) {
        return;
    }

    $sql = file_get_contents(__DIR__ . '/../database.sql');
    if ($sql !== false) {
        db()->exec($sql);
    }

    $config = require __DIR__ . '/config.php';
    $defaultEmail = getenv('ADMIN_EMAIL') ?: 'admin@example.com';
    $envPassword = getenv('ADMIN_PASSWORD');
    $password = $envPassword !== false && $envPassword !== '' ? $envPassword : bin2hex(random_bytes(8));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :password_hash, :role, 1)');
    $stmt->execute([
        'name' => 'Администратор',
        'email' => $defaultEmail,
        'password_hash' => $hash,
        'role' => 'admin',
    ]);

    $storageDir = dirname($config['db_path']);
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }
    $credPath = $storageDir . '/initial-admin-credentials.txt';
    file_put_contents(
        $credPath,
        "Initial admin account created at " . date('c') . "\n" .
        "Email:    {$defaultEmail}\n" .
        "Password: {$password}\n\n" .
        "Войдите, смените пароль и удалите этот файл.\n"
    );
    @chmod($credPath, 0600);
}
