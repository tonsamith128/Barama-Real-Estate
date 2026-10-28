<?php
declare(strict_types=1);
/** Release contract for a broken Invoices GET and light-mode Turnstile row. */
$root = dirname(__DIR__);
$read = static fn(string $p): string => (string) file_get_contents($root . '/' . $p);
$repo = $read('application/app/Repositories/InvoiceTemplateRepository.php');
$migration = $read('application/database/migrations/0032_create_invoice_templates.php');
$partial = $read('application/resources/views/workspace/partials/invoice-template.php');
$css = $read('assets/css/turnstile-admin-modal.css');
$controller = $read('application/app/Http/Controllers/DashboardController.php');
$routes = $read('application/routes/web.php');
$fail = static function (string $msg): never {throw new RuntimeException($msg);};
if (!str_contains($migration, 'CREATE TABLE IF NOT EXISTS invoice_templates (')) $fail('Migration table missing');
if (!str_contains($migration, 'UNIQUE KEY invoice_templates_template_key_unique')) $fail('Unique template identity missing');
if (!preg_match('/INSERT INTO invoice_templates \((.*?)\)\s*VALUES/s', $repo, $match)) $fail('Template write contract missing');
$cols = array_map('trim', explode(',', preg_replace('/\s+/', ' ', $match[1])));
foreach ($cols as $col) if (!preg_match('/^\s*' . preg_quote($col, '/') . '\s+[A-Z]/mi', $migration)) $fail('Missing template column ' . $col);
if (!str_contains($repo, "(string) \$exception->getCode() === '42S02'")) $fail('Unmigrated GET must render safely');
if (!str_contains($partial, 'Invoice template setup is pending.')) $fail('User migration notice missing');
if (!str_contains($partial, 'Apply database migration first')) $fail('Disabled unsafe template action missing');
if (!str_contains($controller, "'workspaceNavigation' => \$this->navigation()")) $fail('Dashboard wrapper navigation missing');
if (!str_contains($routes, "new PermissionMiddleware('billing.manage')")) $fail('Invoice permission gate missing');
foreach (['.br-ts-feature-title','.br-ts-feature-description','.br-ts-td-description','.br-ts-row-btn','.br-ts-status'] as $selector) {
 if (!str_contains($css, ':root[data-bs-theme="light"] .barama-settings--flat ' . $selector)) $fail('Missing light-mode selector ' . $selector);
}
if (!str_contains($css, 'BARAMA_TURNSTILE_TABLE_MODAL_V111')) $fail('Dark modal must remain untouched');
echo "INVOICE_AND_TURNSTILE_V100=PASS (" . count($cols) . " invoice columns)\n";
