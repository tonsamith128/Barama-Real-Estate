<?php
declare(strict_types=1);

require dirname(__DIR__) . '/application/app/Services/SystemToolService.php';

use Barama\Services\SystemToolService;

function insist(bool $ok, string $name): void {
    if (!$ok) { fwrite(STDERR, 'FAIL=' . $name . PHP_EOL); exit(1); }
    echo 'PASS=' . $name . PHP_EOL;
}
$root = dirname(__DIR__);
$service = file_get_contents($root . '/application/app/Services/SystemToolService.php');
$controller = file_get_contents($root . '/application/app/Http/Controllers/SystemToolController.php');
$routes = file_get_contents($root . '/application/routes/web.php');
$layout = file_get_contents($root . '/application/resources/views/layouts/app.php');
$view = file_get_contents($root . '/application/resources/views/workspace/system-tool.php');
$js = file_get_contents($root . '/assets/js/barama-system-tool-v100.js');
$css = file_get_contents($root . '/assets/css/barama-system-tool-v100.css');

insist(str_contains($layout, "['Tool', '/dashboard/system/tool', 'settings']"), 'sidebar_system_tool');
foreach (['/dashboard/system/tool', '/dashboard/system/tool/import', '/dashboard/system/tool/export',
          '/dashboard/system/tool/backup', '/dashboard/system/tool/restore', '/dashboard/system/tool/download'] as $path) {
    insist(str_contains($routes, "'" . $path . "'"), 'route_' . basename($path));
}
insist(substr_count((string)$controller, "if (!$this->admin())") === 6, 'admin_enforced_on_every_operation');
insist(str_contains($routes, 'new CsrfMiddleware()'), 'csrf_middleware_on_writes');
insist(str_contains($js, 'change_confirmation') && str_contains($js, '/security/change-confirmation')
    && str_contains($js, 'requestGrant'), 'passcode_single_use_grant_flow');
insist(str_contains($view, 'data-btool-protected') && str_contains($view, 'RESTORE DATABASE'),
    'restore_explicit_approval');
insist(str_contains($view, 'database') && str_contains($view, 'media'),
    'database_only_scope_documented');
foreach (['importer','exporter','backup','restore'] as $tab) {
    insist(str_contains($view, "'" . $tab . "'"), 'tab_' . $tab);
}
insist(str_contains($css, '[data-bs-theme="light"]') && str_contains($css, '@media(max-width:850px)'),
    'premium_light_dark_responsive');
insist(str_contains($service, 'LOCK_EX | LOCK_NB') && str_contains($service, 'private function exclusive('),
    'file_lock_for_mutations');
insist(str_contains($service, '$this->verifiedBackup($id)')
    && str_contains($service, '$safety = $this->backup()')
    && str_contains($service, "SET FOREIGN_KEY_CHECKS=1"),
    'restore_local_snapshot_safety_backup');
insist(!str_contains($service, 'shell_exec(') && !str_contains($service, 'exec(' . '$user')
    && !str_contains($controller, 'move_uploaded_file('),
    'no_arbitrary_sql_or_shell_restore');

$ident = new ReflectionMethod(SystemToolService::class, 'ident');
$valid = new ReflectionMethod(SystemToolService::class, 'validId');
insist($ident->invoke(null, 'properties') === chr(96) . 'properties' . chr(96),
    'allowlisted_sql_identifier');
$blocked = false;
try { $ident->invoke(null, 'properties;DROP TABLE'); } catch (RuntimeException) { $blocked = true; }
insist($blocked, 'sql_identifier_injection_denied');
insist($valid->invoke(null, '20261024000000-0123456789abcdef') === true &&
    $valid->invoke(null, '../evil.sql') === false, 'private_backup_id_validated');
$blocked = false;
try { (new SystemToolService())->verifiedBackup('../evil.zip'); }
catch (RuntimeException $e) { $blocked = $e->getMessage() === 'BACKUP_ID_INVALID'; }
insist($blocked, 'arbitrary_restore_path_denied');
echo "SYSTEM_TOOL_STATIC_CONTRACT_TESTS=PASS\n";
