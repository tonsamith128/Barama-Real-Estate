<?php

declare(strict_types=1);

use Barama\Services\EmailTemplateCatalog;
use Barama\Services\EmailTemplateManagementService;

$templates = is_array($emailTemplates ?? null) ? $emailTemplates : [];
$summary = is_array($emailTemplateSummary ?? null) ? $emailTemplateSummary : [];
$catalog = EmailTemplateCatalog::definitions();
$templatesByKey = [];
$customTemplates = [];

foreach ($templates as $template) {
    $key = strtolower(trim((string) ($template['template_key'] ?? '')));
    if ($key === '') {
        continue;
    }

    $templatesByKey[$key] = $template;

    if (EmailTemplateCatalog::get($key) === null && EmailTemplateManagementService::isCustomKey($key)) {
        $customTemplates[] = $template;
    }
}

$groups = [];
foreach ($catalog as $definition) {
    $group = (string) ($definition['group'] ?? 'Other');
    $groups[$group] = true;
}
if ($customTemplates !== []) {
    $groups['Custom'] = true;
}

$totalRows = count($catalog) + count($customTemplates);
$statusTone = static fn (string $status): string => match ($status) {
    'active' => 'active',
    'inactive' => 'inactive',
    'archived' => 'archived',
    default => 'fallback',
};
?>
<link rel="stylesheet" href="<?= e(asset('css/admin-email-templates-v142.css') . '?v=142') ?>">

<section class="workspace-page email-template-manager" data-email-template-manager data-email-template-version="142">
    <div class="workspace-page__header email-template-manager__hero">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Email Templates</h1>
            <p>Manage Barama system emails and reusable custom templates from one catalog. Configure opens the full Email Template Builder with multilingual variants, preview, revisions and lifecycle controls.</p>
        </div>
        <a class="email-template-create-new" href="<?= e(url('/dashboard/email-templates/create')) ?>">
            <?= app_icon('edit') ?>
            <span>Create New</span>
        </a>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="email-template-metrics" aria-label="Email template summary">
        <article><span><?= app_icon('layers') ?></span><div><strong><?= e(number_format((int) ($summary['total'] ?? 0))) ?></strong><small>Configured templates</small></div></article>
        <article><span><?= app_icon('check') ?></span><div><strong><?= e(number_format((int) ($summary['active'] ?? 0))) ?></strong><small>Active</small></div></article>
        <article><span><?= app_icon('edit') ?></span><div><strong><?= e(number_format((int) ($summary['inactive'] ?? 0))) ?></strong><small>Inactive</small></div></article>
        <article><span><?= app_icon('layers') ?></span><div><strong><?= e(number_format((int) ($summary['archived'] ?? 0))) ?></strong><small>Archived</small></div></article>
    </div>

    <section class="email-template-panel">
        <header class="email-template-panel__head">
            <div>
                <h2>Email catalog</h2>
                <p>The 17 governed system email types keep their original safe fallback. Custom templates are standalone reusable templates and always start inactive.</p>
            </div>
            <span class="email-template-panel__count"><span data-email-template-visible-count><?= e((string) $totalRows) ?></span> of <?= e((string) $totalRows) ?> shown</span>
        </header>

        <div class="email-template-panel__toolbar">
            <label>
                <?= app_icon('search') ?>
                <input type="search" placeholder="Search email templates" data-email-template-search aria-label="Search email templates">
            </label>
            <label>
                <select data-email-template-group-filter aria-label="Filter email templates by category">
                    <option value="">All categories</option>
                    <?php foreach (array_keys($groups) as $group): ?>
                        <option value="<?= e($group) ?>"><?= e($group) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="email-template-table-wrap">
            <table class="email-template-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Delivery source</th>
                        <th>Subject</th>
                        <th>Updated</th>
                        <th class="email-template-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catalog as $key => $definition): ?>
                        <?php
                        $template = $templatesByKey[$key] ?? null;
                        $status = is_array($template) ? (string) ($template['status'] ?? 'inactive') : 'fallback';
                        $tone = $statusTone($status);
                        $label = (string) ($definition['label'] ?? $key);
                        $group = (string) ($definition['group'] ?? 'Other');
                        $description = (string) ($definition['description'] ?? '');
                        $searchText = $label . ' ' . $key . ' ' . $group . ' ' . $description;
                        ?>
                        <tr class="email-template-row" data-email-template-row data-group="<?= e($group) ?>" data-search="<?= e($searchText) ?>">
                            <td>
                                <div class="email-template-identity">
                                    <span class="email-template-identity__icon"><?= app_icon(!empty($definition['security_sensitive']) ? 'shield' : 'mail') ?></span>
                                    <span><strong><?= e($label) ?></strong><small><?= e($key) ?></small></span>
                                </div>
                            </td>
                            <td><span class="email-template-category"><?= e($group) ?></span></td>
                            <td><span class="email-template-status email-template-status--<?= e($tone) ?>"><span aria-hidden="true"></span><?= e($status === 'fallback' ? 'System fallback' : ucfirst($status)) ?></span></td>
                            <td>
                                <?php if (is_array($template)): ?>
                                    <strong class="email-template-source"><?= e((string) ($template['name'] ?? $label)) ?></strong>
                                    <small class="email-template-source-note">Custom override</small>
                                <?php else: ?>
                                    <strong class="email-template-source">Barama default</strong>
                                    <small class="email-template-source-note">No custom override</small>
                                <?php endif; ?>
                            </td>
                            <td class="email-template-subject"><?= e(is_array($template) ? (string) ($template['subject_template'] ?? '') : (string) (($definition['defaults']['subject'] ?? 'System-generated subject'))) ?></td>
                            <td class="email-template-updated">
                                <?php if (is_array($template)): ?><strong><?= e((string) ($template['updated_at'] ?? '')) ?></strong><small><?= e((string) ($template['updated_by_name'] ?? 'System')) ?></small><?php else: ?><span>—</span><?php endif; ?>
                            </td>
                            <td class="email-template-table__actions"><a class="email-template-configure email-template-configure--link" href="<?= e(url('/dashboard/email-templates/' . rawurlencode($key) . '/builder')) ?>">Configure</a></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($customTemplates as $template): ?>
                        <?php
                        $key = strtolower(trim((string) ($template['template_key'] ?? '')));
                        $status = (string) ($template['status'] ?? 'inactive');
                        $tone = $statusTone($status);
                        $label = trim((string) ($template['name'] ?? 'Custom email template'));
                        $description = trim((string) ($template['description'] ?? 'Standalone reusable custom email template.'));
                        $searchText = $label . ' ' . $key . ' Custom ' . $description;
                        ?>
                        <tr class="email-template-row email-template-row--custom" data-email-template-row data-group="Custom" data-search="<?= e($searchText) ?>">
                            <td>
                                <div class="email-template-identity">
                                    <span class="email-template-identity__icon email-template-identity__icon--custom"><?= app_icon('mail') ?></span>
                                    <span><strong><?= e($label !== '' ? $label : 'Custom email template') ?></strong><small><?= e($key) ?></small></span>
                                </div>
                            </td>
                            <td><span class="email-template-category email-template-category--custom">Custom</span></td>
                            <td><span class="email-template-status email-template-status--<?= e($tone) ?>"><span aria-hidden="true"></span><?= e(ucfirst($status)) ?></span></td>
                            <td><strong class="email-template-source">Standalone template</strong><small class="email-template-source-note">Reusable custom content</small></td>
                            <td class="email-template-subject"><?= e((string) ($template['subject_template'] ?? '')) ?></td>
                            <td class="email-template-updated"><strong><?= e((string) ($template['updated_at'] ?? '')) ?></strong><small><?= e((string) ($template['updated_by_name'] ?? 'System')) ?></small></td>
                            <td class="email-template-table__actions"><a class="email-template-configure email-template-configure--link" href="<?= e(url('/dashboard/email-templates/' . rawurlencode($key) . '/builder')) ?>">Configure</a></td>
                        </tr>
                    <?php endforeach; ?>

                    <tr data-email-template-filter-empty hidden>
                        <td colspan="7"><div class="email-template-empty-filter">No email templates match the current search or category.</div></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer class="email-template-panel__foot">
            <span><?= app_icon('check') ?></span>
            <p><strong>Safe lifecycle:</strong> new overrides and custom templates start inactive. Active templates must be deactivated before editing. The 17 governed email types continue to use Barama's original fallback whenever no active override exists.</p>
        </footer>
    </section>
</section>

<script src="<?= e(asset('js/admin-email-templates-v142.js') . '?v=142') ?>" defer></script>
