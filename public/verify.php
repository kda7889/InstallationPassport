<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$number = (string) ($_GET['n'] ?? '');
$code = (string) ($_GET['c'] ?? '');
$query = $number !== '' || $code !== '' ? '?n=' . urlencode($number) . '&c=' . urlencode($code) : '';
redirect('/customer.php' . $query);
