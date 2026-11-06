<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';

// Signing out changes state, so it only works as a POST with a valid CSRF token (a link or image cannot log you out).
if (!is_post()) {
    redirect('dashboard.php');
}
csrf_verify();
perform_logout();
session_boot();
flash('success', 'You have been signed out.');
redirect('login.php');
