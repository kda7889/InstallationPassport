<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/image.php';

db_bootstrap_if_needed();
