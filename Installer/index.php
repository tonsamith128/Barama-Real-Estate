<?php

declare(strict_types=1);

use Barama\Installer\Setup;

require_once __DIR__.'/Setup.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$root = Setup::root();
if (!Setup::documentRootAllowed($_SERVER)) {
    http_response_code(403);
    exit('Unsafe website document root. Point the domain to public/ or activate the protected cPanel root bridge.');
}

$https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
$https = $https || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
$serverHost = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$localhost = in_array($serverHost, ['localhost', '127.0.0.1', '::1'], true);
if (!$https && !$localhost) {
    http_response_code(403);
    exit('HTTPS is required to securely submit database and administrator credentials.');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('barama_installer');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/installer',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

if (isset($_SESSION['started']) && time() - (int) $_SESSION['started'] > 1200) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['started'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$_SESSION['stage'] ??= 1;

$labels = [1 => 'Requirements', 2 => 'Database', 3 => 'Application', 4 => 'Administrator', 5 => 'Finish'];
$step = filter_var($_GET['step'] ?? 1, FILTER_VALIDATE_INT);
$step = $step !== false && $step >= 1 && $step <= 5 ? $step : 1;
$step = min($step, (int) $_SESSION['stage']);
$completed = Setup::installed() && !empty($_SESSION['completed']);
$error = '';

if (Setup::installed() && !$completed) {
    http_response_code(410);
    exit('Barama is already installed. Remove the Installer and public/installer directories.');
}
if (!Setup::installed() && file_exists($root.'/.env')) {
    http_response_code(409);
    exit('Existing .env detected. This installer only supports a fresh installation; no files or database tables were changed.');
}
if ($completed) {
    $step = 5;
} elseif ($step === 1) {
    try {
        Setup::ensureToken();
    } catch (Throwable $e) {
        $error = 'Private storage/app must be writable before beginning setup.';
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedStep = filter_var($_POST['step'] ?? null, FILTER_VALIDATE_INT);
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf'], $csrf)) {
        http_response_code(419);
        $error = 'Session expired or invalid form token. Reload this page.';
    } elseif ($completed || $postedStep !== $step || $postedStep > 4) {
        http_response_code(400);
        $error = 'Invalid installation step. Reload this page.';
    } else {
        try {
            if ($step === 1) {
                if (!Setup::verifyToken((string) ($_POST['token'] ?? ''))) {
                    throw new RuntimeException('Invalid setup token. Open storage/app/installer.token using your hosting File Manager.');
                }
                if (!Setup::requirementsPassed()) {
                    throw new RuntimeException('Please resolve the failed requirements below.');
                }
                session_regenerate_id(true);
                $_SESSION['authorized'] = true;
                $_SESSION['stage'] = 2;
            } elseif (empty($_SESSION['authorized'])) {
                throw new RuntimeException('Verify the private setup token first.');
            } elseif ($step === 2) {
                $db = [
                    'host' => trim((string) ($_POST['db_host'] ?? '')),
                    'port' => (string) ($_POST['db_port'] ?? ''),
                    'database' => trim((string) ($_POST['db_name'] ?? '')),
                    'username' => trim((string) ($_POST['db_user'] ?? '')),
                    'password' => (string) ($_POST['db_password'] ?? ''),
                ];
                Setup::validateDatabase($db);
                try {
                    $empty = Setup::emptyDatabase($db);
                } catch (Throwable $e) {
                    throw new RuntimeException('Cannot connect to MySQL. Verify the database, host and credentials.');
                }
                if (!$empty) {
                    throw new RuntimeException('This database contains tables or views. Create a separate empty database.');
                }
                $_SESSION['database'] = $db;
                $_SESSION['stage'] = 3;
            } elseif ($step === 3) {
                $_SESSION['app_url'] = Setup::validateApplication((string) ($_POST['app_url'] ?? ''));
                $_SESSION['stage'] = 4;
            } elseif ($step === 4) {
                if (!is_array($_SESSION['database'] ?? null) || !is_string($_SESSION['app_url'] ?? null)) {
                    throw new RuntimeException('Database and application configuration are incomplete.');
                }
                $admin = [
                    'name' => (string) ($_POST['admin_name'] ?? ''),
                    'email' => (string) ($_POST['admin_email'] ?? ''),
                    'password' => (string) ($_POST['admin_password'] ?? ''),
                    'password_confirmation' => (string) ($_POST['admin_confirm'] ?? ''),
                ];
                Setup::validateAdministrator($admin);
                try {
                    Setup::finish($_SESSION['database'], $_SESSION['app_url'], $admin);
                } catch (Throwable $e) {
                    error_log('Barama installer failure (no credentials logged): '.$e::class);
                    throw new RuntimeException('Installation could not finish. Check server logs. If tables or .env were created, do NOT retry against that database; investigate and use a fresh database after recovery.');
                } finally {
                    unset($admin, $_POST['admin_password'], $_POST['admin_confirm']);
                }
                $_SESSION = [
                    'started' => time(),
                    'csrf' => bin2hex(random_bytes(32)),
                    'stage' => 5,
                    'completed' => true,
                ];
                session_regenerate_id(true);
            }
            if ($error === '') {
                header('Location: /installer/?step='.$_SESSION['stage'], true, 303);
                exit;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

function h(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function field(string $id, string $label, string $type, string $value = '', string $attr = ''): void
{
    echo '<label for="'.h($id).'">'.h($label).'</label>';
    echo '<input id="'.h($id).'" name="'.h($id).'" type="'.h($type).'" value="'.h($value).'" required '.$attr.'>';
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Barama Setup — <?= h($labels[$step]) ?></title>
<style>
:root{color-scheme:light;font-family:system-ui,-apple-system,sans-serif;background:#f4f7fc;color:#16263e}
*{box-sizing:border-box}body{margin:0;padding:32px 16px}main{max-width:710px;margin:auto;background:#fff;border:1px solid #e3eaf3;border-radius:18px;box-shadow:0 12px 48px #18284816;padding:32px}
h1{margin:0 0 8px;font-size:27px}p{line-height:1.65;color:#526377}nav{display:flex;gap:5px;flex-wrap:wrap;margin:24px 0}nav span{flex:1;min-width:100px;border-top:4px solid #dbe3f0;padding:9px 4px;font-size:12px;color:#63748c}
nav .active{border-color:#ee8a24;color:#152a46;font-weight:700}nav .done{border-color:#1775bb}
label{display:block;margin:17px 0 7px;font-weight:650;font-size:14px}input{display:block;width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:12px 14px;font-size:15px}
button{background:#ed831b;color:#fff;border:0;border-radius:10px;padding:13px 18px;margin-top:22px;font-size:15px;font-weight:700;cursor:pointer}
small{color:#62738b}.error{padding:12px 16px;background:#fff1f1;border:1px solid #f4b9b9;color:#9e2929;border-radius:9px}
.notice{background:#eef6fc;border:1px solid #c6ddef;padding:13px;border-radius:10px}
ul{padding-left:24px;line-height:1.8}.good{color:#167449}.bad{color:#b52222}code{overflow-wrap:anywhere}
a{color:#086cba}.footer{margin-top:26px;padding-top:16px;border-top:1px solid #e5edf6;font-size:13px;color:#62738b}
</style>
</head>
<body><main>
<h1>Barama Real Estate</h1>
<p>Independent Laravel 13 setup • Fresh installation only</p>
<nav aria-label="Installer progress">
<?php foreach ($labels as $number => $label): ?>
<span class="<?= $number === $step ? 'active' : ($number < $step ? 'done' : '') ?>"><?= $number ?>. <?= h($label) ?></span>
<?php endforeach; ?>
</nav>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($step === 5): ?>
<h2>Installation completed</h2>
<p>Your new Laravel database and first administrator were created, and the installation lock is active.</p>
<p class="notice"><strong>Remove both</strong> <code>Installer/</code> and <code>public/installer/</code> using your hosting File Manager. Do not remove <code>storage/app/installed.lock</code>.</p>
<p><a href="/login">Go to the login page</a></p>
<?php else: ?>
<form method="post" action="/installer/?step=<?= $step ?>" autocomplete="off">
<input type="hidden" name="step" value="<?= $step ?>">
<input type="hidden" name="csrf" value="<?= h((string) $_SESSION['csrf']) ?>">
<?php if ($step === 1): ?>
<h2>1. Requirements</h2>
<p>Retrieve the private setup token from <code>storage/app/installer.token</code> using your hosting File Manager or SSH. The value is never displayed publicly.</p>
<?php foreach (Setup::requirements() as $label => $passed): ?>
<div class="<?= $passed ? 'good' : 'bad' ?>"><?= $passed ? '✓' : '✗' ?> <?= h($label) ?></div>
<?php endforeach; ?>
<?php field('token', 'Private setup token', 'password', '', 'minlength="64" maxlength="64" autocomplete="off"'); ?>
<button type="submit">Verify requirements</button>
<?php elseif ($step === 2): ?>
<h2>2. Database</h2>
<p>Use a newly created, empty MySQL database. Existing Barama data cannot be upgraded with this installer.</p>
<?php field('db_host', 'MySQL host', 'text', '127.0.0.1'); ?>
<?php field('db_port', 'MySQL port', 'number', '3306', 'min="1" max="65535"'); ?>
<?php field('db_name', 'Database name', 'text'); ?>
<?php field('db_user', 'Database username', 'text'); ?>
<?php field('db_password', 'Database password', 'password', '', 'autocomplete="new-password"'); ?>
<button type="submit">Test empty database</button>
<?php elseif ($step === 3): ?>
<h2>3. Application</h2>
<p>Enter the final HTTPS URL. The installer will generate a unique application encryption key and safe production settings.</p>
<?php field('app_url', 'Application URL', 'url', 'https://'); ?>
<button type="submit">Continue to administrator</button>
<?php elseif ($step === 4): ?>
<h2>4. Administrator</h2>
<p>Submitting this form runs new-schema Laravel migrations and creates the initial <strong>super administrator</strong>. Do not close the browser while it completes.</p>
<?php field('admin_name', 'Administrator name', 'text'); ?>
<?php field('admin_email', 'Administrator email', 'email'); ?>
<?php field('admin_password', 'Password (minimum 12 characters)', 'password', '', 'minlength="12" autocomplete="new-password"'); ?>
<?php field('admin_confirm', 'Confirm password', 'password', '', 'minlength="12" autocomplete="new-password"'); ?>
<button type="submit">Install Barama Real Estate</button>
<?php endif; ?>
</form>
<?php endif; ?>
<div class="footer">For security, setup requires HTTPS, an empty database, a private setup token, and a correctly configured <code>public/</code> document root.</div>
</main></body></html>
