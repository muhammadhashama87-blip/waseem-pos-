<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}
$error = null; $username = '';
if (is_post()) {
    csrf_verify();
    $username = strtolower(post_str('username'));
    $res = attempt_login($username, (string)($_POST['password'] ?? ''));
    if ($res['ok']) {
        $next = safe_next($_SESSION['next'] ?? null);
        unset($_SESSION['next']);
        redirect($next ?? 'dashboard.php');
    }
    $error = $res['error'];
    http_response_code(401);
}
render_public_header('Sign in');
?>
<h1>Sign in</h1>
<p class="lead">Use the account the Admin created for you.</p>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack"><?= csrf_field() ?>
  <div class="field"><label for="username">Username</label><input id="username" name="username" type="text" value="<?= e($username) ?>" required maxlength="40" autocomplete="username" autocapitalize="none" autofocus></div>
  <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary" type="submit" style="width:100%">Sign in</button>
</form>
<?php render_public_footer();
