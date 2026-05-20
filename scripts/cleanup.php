<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$days = (int) ($argv[1] ?? 365);
if ($days < 1) {
    fwrite(STDERR, "Usage: php scripts/cleanup.php [days_to_keep=365]\n");
    exit(1);
}

$deleted = audit_log_cleanup($days);
echo "Deleted {$deleted} audit_log rows older than {$days} days.\n";
