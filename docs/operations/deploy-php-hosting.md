# Deploy on Shared PHP Hosting

1. Установить PHP 8.x с `pdo_sqlite`, `gd`, `exif`.
2. Point web root to `/public`.
3. Убедиться, что `/storage` writable веб-пользователем.
4. Запретить выполнение PHP внутри `/storage` через `.htaccess`/server config.
5. Настроить HTTPS и secure cookies.
