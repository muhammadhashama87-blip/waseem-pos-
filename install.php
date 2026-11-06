<?php
declare(strict_types=1);
// First-time installer: (1) database connection -> (2) create tables -> (3) create the Admin account.
// Refuses to run again once an Admin exists.
define('INSTALLER', true);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/layout.php';

const LOCK_FILE = APP_ROOT . '/storage/installed.lock';

if (PHP_VERSION_ID < 80100 || !extension_loaded('pdo_mysql')) {
    render_public_header('Requirements');
    echo '<h1>Server requirements</h1><div class="alert error">This system needs PHP 8.1 or newer with the pdo_mysql extension. This server has PHP ' . e(PHP_VERSION) . '.</div>';
    render_public_footer();
    exit;
}

/** Run every statement of an .sql file (comment lines are skipped). */
function run_sql_file(PDO $pdo, string $file): void
{
    $sql = (string)file_get_contents($file);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $stmt) {
        $clean = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
        if ($clean !== '') {
            $pdo->exec($clean);
        }
    }
}

function tables_exist(PDO $pdo): bool
{
    return (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'")->fetchColumn() > 0;
}

function done_page(string $title, string $msg): never
{
    http_response_code(403);
    render_public_header($title);
    echo '<h1>' . e($title) . '</h1><p>' . e($msg) . '</p><p><a class="btn btn-primary" href="' . e(url('login.php')) . '">Go to sign in</a></p>';
    render_public_footer();
    exit;
}

// ---- Work out where we are ----
$pdo = null; $dbError = null; $usersCount = 0; $tables = false;
if (app_config_exists()) {
    try {
        $pdo = db();
        $tables = tables_exist($pdo);
        $usersCount = $tables ? (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() : 0;
    } catch (Throwable $e) {
        $dbError = 'The saved database settings no longer work. Fix or delete config/config.php, then reload this page.';
    }
}
if ($usersCount > 0) {
    if (!is_file(LOCK_FILE)) {
        @file_put_contents(LOCK_FILE, "installed\n");
    }
    done_page('Already installed', 'An Admin account already exists, so the installer is switched off. Sign in instead.');
}

$step = !app_config_exists() ? 1 : (!$tables ? 2 : 3);
$errors = []; $old = [];

if (is_post() && $dbError === null) {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'save_db' && $step === 1) {
        $old = ['db_host' => post_str('db_host'), 'db_port' => post_str('db_port'), 'db_name' => post_str('db_name'), 'db_user' => post_str('db_user')];
        $pass = (string)($_POST['db_pass'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]{1,100}$/', $old['db_host'])) { $errors[] = 'Enter a valid database host (usually 127.0.0.1 or localhost).'; }
        if (!ctype_digit($old['db_port']) || (int)$old['db_port'] < 1 || (int)$old['db_port'] > 65535) { $errors[] = 'Port must be a number (usually 3306).'; }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $old['db_name'])) { $errors[] = 'Database name may use letters, numbers and underscore only.'; }
        if ($old['db_user'] === '') { $errors[] = 'Enter the database user.'; }
        if (!$errors) {
            $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            $base = "mysql:host={$old['db_host']};port={$old['db_port']};charset=utf8mb4";
            try {
                try {
                    new PDO($base . ";dbname={$old['db_name']}", $old['db_user'], $pass, $opts);
                } catch (PDOException $e) {
                    if ((int)$e->getCode() !== 1049) { throw $e; }       // 1049 = database does not exist yet: try to create it
                    $root = new PDO($base, $old['db_user'], $pass, $opts);
                    $root->exec('CREATE DATABASE `' . $old['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    new PDO($base . ";dbname={$old['db_name']}", $old['db_user'], $pass, $opts);
                }
                $cfg = ['db' => ['host' => $old['db_host'], 'port' => (int)$old['db_port'], 'name' => $old['db_name'], 'user' => $old['db_user'], 'pass' => $pass, 'charset' => 'utf8mb4'],
                        'app' => ['name' => 'WASEEM PAINTS & HARDWARE STORE', 'timezone' => 'Asia/Karachi', 'base_path' => '', 'debug' => false]];
                $php = "<?php\n// Created by install.php. Keep private.\nreturn " . var_export($cfg, true) . ";\n";
                if (@file_put_contents(APP_ROOT . '/config/config.php', $php) === false) {
                    $errors[] = 'Connected, but config/config.php could not be written. Make the config folder writable, or create the file by hand from config.sample.php.';
                } else {
                    flash('success', 'Database connection saved.');
                    redirect('install.php');
                }
            } catch (PDOException $e) {
                error_log('installer db: ' . $e->getMessage());
                $errors[] = 'Could not connect with those details (' . (int)$e->getCode() . '). Check that MySQL is running and the user, password and name are right.';
            }
        }
    } elseif ($action === 'create_tables' && $step === 2) {
        try {
            run_sql_file($pdo, APP_ROOT . '/database/install.sql');
            run_sql_file($pdo, APP_ROOT . '/database/migrations/002_stage2.sql');
            flash('success', 'Database tables created.');
            redirect('install.php');
        } catch (Throwable $e) {
            error_log('installer sql: ' . $e->getMessage());
            $errors[] = 'Creating tables failed: ' . $e->getMessage() . ' If some tables were created, empty this database in phpMyAdmin and try again.';
        }
    } elseif ($action === 'create_admin' && $step === 3) {
        $old = ['business_name' => post_str('business_name'), 'full_name' => post_str('full_name'), 'username' => strtolower(post_str('username'))];
        $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');
        foreach ([text_len_error('Business name', $old['business_name'], 2, 100), text_len_error('Your full name', $old['full_name'], 2, 100),
                  username_error($old['username']), password_error($pw)] as $m) {
            if ($m) { $errors[] = $m; }
        }
        if ($pw !== $pw2) { $errors[] = 'The two passwords do not match.'; }
        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if ((int)$pdo->query('SELECT COUNT(*) FROM users FOR UPDATE')->fetchColumn() > 0) {
                    throw new RuntimeException('An Admin already exists.');
                }
                $roleId = (int)$pdo->query("SELECT id FROM roles WHERE code = 'admin'")->fetchColumn();
                $pdo->prepare('INSERT INTO users (username, full_name, password_hash, role_id, is_active, must_change_password) VALUES (?,?,?,?,1,0)')
                    ->execute([$old['username'], $old['full_name'], password_hash($pw, PASSWORD_DEFAULT), $roleId]);
                $adminId = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'business_name'")->execute([$old['business_name']]);
                $pdo->commit();
                audit($adminId, 'install.completed', 'user', (string)$adminId, ['username' => $old['username']]);
                @file_put_contents(LOCK_FILE, 'installed ' . date('c') . "\n");
                flash('success', 'Installation complete. Sign in with the Admin account you just created.');
                redirect('login.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('installer admin: ' . $e->getMessage());
                $errors[] = 'Could not create the Admin account: ' . $e->getMessage();
            }
        }
    }
}

// ---- Page ----
render_public_header('Install', 'wide');
$label = static fn(int $n, string $t): string => '<span class="' . ($step > $n ? 'done' : ($step === $n ? 'now' : '')) . '">' . $n . '. ' . e($t) . '</span>';
echo '<div class="steps">' . $label(1, 'Database') . $label(2, 'Tables') . $label(3, 'Admin account') . '</div>';
if ($dbError) {
    echo '<div class="alert error">' . e($dbError) . '</div>';
}
if ($errors) {
    echo '<div class="alert error"><b>Please fix this:</b><ul>';
    foreach ($errors as $m) { echo '<li>' . e($m) . '</li>'; }
    echo '</ul></div>';
}
if ($dbError === null && $step === 1) { ?>
  <h1>Connect the database</h1>
  <p class="lead">Create or choose an empty MySQL database. On XAMPP the defaults below work: user <code class="k">root</code>, no password.</p>
  <form method="post" class="stack" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="save_db">
    <div class="form-grid">
      <div class="field"><label for="db_host">Host</label><input id="db_host" name="db_host" type="text" value="<?= e($old['db_host'] ?? '127.0.0.1') ?>" required></div>
      <div class="field"><label for="db_port">Port</label><input id="db_port" name="db_port" type="text" value="<?= e($old['db_port'] ?? '3306') ?>" required></div>
      <div class="field"><label for="db_name">Database name</label><input id="db_name" name="db_name" type="text" value="<?= e($old['db_name'] ?? 'waseem_pos') ?>" required><div class="hint">Created for you if it does not exist.</div></div>
      <div class="field"><label for="db_user">Database user</label><input id="db_user" name="db_user" type="text" value="<?= e($old['db_user'] ?? 'root') ?>" required></div>
      <div class="field"><label for="db_pass">Database password</label><input id="db_pass" name="db_pass" type="password" autocomplete="new-password"></div>
    </div>
    <button class="btn btn-primary" type="submit">Test and save connection</button>
  </form>
<?php } elseif ($dbError === null && $step === 2) { ?>
  <h1>Create the database tables</h1>
  <p class="lead">This creates all 40 tables plus the standard roles, permissions, units and expense categories. No business data is added.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create_tables"><button class="btn btn-primary" type="submit">Create tables</button></form>
<?php } elseif ($dbError === null) { ?>
  <h1>Create the Admin account</h1>
  <p class="lead">This account has full access. Use a strong password you do not use anywhere else.</p>
  <form method="post" class="stack" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="create_admin">
    <div class="form-grid">
      <div class="field"><label for="business_name">Business name</label><input id="business_name" name="business_name" type="text" value="<?= e($old['business_name'] ?? 'WASEEM PAINTS & HARDWARE STORE') ?>" required maxlength="100"></div>
      <div class="field"><label for="full_name">Your full name</label><input id="full_name" name="full_name" type="text" value="<?= e($old['full_name'] ?? '') ?>" required maxlength="100"></div>
      <div class="field"><label for="username">Admin username</label><input id="username" name="username" type="text" value="<?= e($old['username'] ?? '') ?>" required maxlength="40" autocapitalize="none"><div class="hint">Lowercase letters, numbers, dot, dash, underscore.</div></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password"><div class="hint">At least 8 characters with a letter and a number.</div></div>
      <div class="field"><label for="password2">Repeat password</label><input id="password2" name="password2" type="password" required autocomplete="new-password"></div>
    </div>
    <button class="btn btn-primary" type="submit">Create Admin and finish</button>
  </form>
<?php }
render_public_footer();
