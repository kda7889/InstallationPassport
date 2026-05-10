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

    $defaultEmail = 'admin@example.com';
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (:name, :email, :password_hash, :role, 1)');
    $stmt->execute([
        'name' => 'Администратор',
        'email' => $defaultEmail,
        'password_hash' => $hash,
        'role' => 'admin',
    ]);
}
