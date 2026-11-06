<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();            // allowed even while a password change is forced
$forced = (int)$user['must_change_password'] === 1;
$errors = [];
if (is_post()) {
    csrf_verify();
    $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $new2 = (string)($_POST['new2'] ?? '');
    $hash = (string)db()->query('SELECT password_hash FROM users WHERE id = ' . (int)$user['id'])->fetchColumn();
    if (!password_verify($cur, $hash)) { $errors[] = 'Your current password is wrong.'; }
    if ($m = password_error($new)) { $errors[] = $m; }
    if ($new !== $new2) { $errors[] = 'The two new passwords do not match.'; }
    if ($cur !== '' && $cur === $new) { $errors[] = 'The new password must be different from the current one.'; }
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        audit((int)$user['id'], 'user.password_change', 'user', (string)$user['id']);
        flash('success', 'Your password was changed.');
        redirect('dashboard.php');
    }
}
render_header('Change password');
?>
<div class="page-head"><div><h1>Change password</h1><?php if ($forced): ?><p>You must choose a new password before you can continue.</p><?php endif; ?></div></div>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="card" style="max-width:520px"><div class="card-body">
  <form method="post" class="stack" autocomplete="off"><?= csrf_field() ?>
    <div class="field"><label for="current">Current password</label><input id="current" name="current" type="password" required autocomplete="current-password"></div>
    <div class="field"><label for="new">New password</label><input id="new" name="new" type="password" required autocomplete="new-password"><div class="hint">At least 8 characters with a letter and a number.</div></div>
    <div class="field"><label for="new2">Repeat new password</label><input id="new2" name="new2" type="password" required autocomplete="new-password"></div>
    <button class="btn btn-primary" type="submit">Save new password</button>
  </form>
</div></section>
<?php render_footer();
