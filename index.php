<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';

try {
    $installed = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (Throwable $e) {
    $installed = false;
}
redirect(!$installed ? 'install.php' : (current_user() ? 'dashboard.php' : 'login.php'));
