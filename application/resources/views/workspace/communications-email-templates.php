<?php

declare(strict_types=1);

$templates = is_array($emailTemplates ?? null) ? $emailTemplates : [];
$summary = is_array($emailTemplateSummary ?? null) ? $emailTemplateSummary : [];
$supportedKeys = is_array($supportedTemplateKeys ?? null)
    ? $supportedTemplateKeys
    : [];

$variables = [
    '{{title}}',
    '{{message}}',
    '{{action_url}}',
    '{{recipient_name}}',
    '{{app_name}}',
    '{{app_url}}',
];
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Email Templates</h1>
            <p>Customize notification-generated email content without changing the durable outbox, SMTP transport, or security-token mail flows.</p>
        </div>
        <span class="workspace-context-note">
            <?= app_icon('mail') ?>
            Queue-time rendering · immutable delivery snapshot
        </span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['total'] ?? 0))) ?></strong><small>Total templates</small></div>
        <div class="workspace-stat"><span><?= app_icon('edit') ?></span><strong><?= e(number_format((int) ($summary['inactive'] ?? 0))) ?></strong><small>Inactive</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int) ($summary['active'] ?? 0))) ?></strong><small>Active</small></div>
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int) ($summary['archived'] ?? 0))) ?></strong><small>Archived</small></div>
    </div>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Create notification email template</h2>
                <p>Templates start inactive. Activate only after reviewing the subject, HTML, and plain-text versions.</p>
            </div>
        </div>

        <form action="<?= e(url('/dashboard/email-templates')) ?>" method="post" data-workspace-form class="workspace-form-grid">
            <?= csrf_field() ?>

            <label>
                <span>Notification type</span>
                <select name="template_key" required>
                    <option value="">Choose notification type</option>
                    <?php foreach ($supportedKeys as $key => $label): ?>
                        <option value="<?= e((string) $key) ?>"><?= e((string) $label) ?> · <?= e((string) $key) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Template name</span>
                <input type="text" name="name" maxlength="160" required placeholder="Viewing confirmed email">
            </label>

            <label class="workspace-form-grid__wide">
                <span>Description</span>
                <input type="text" name="description" maxlength="2000" placeholder="Optional internal purpose or editing note">
            </label>

            <label class="workspace-form-grid__wide">
                <span>Subject template</span>
                <input type="text" name="subject_template" maxlength="255" required placeholder="{{title}} · {{app_name}}">
            </label>

            <label class="workspace-form-grid__wide">
                <span>HTML template</span>
                <textarea name="html_template" rows="8" maxlength="20000" required placeholder="<p>Hello {{recipient_name}},</p><p>{{message}}</p><p><a href=&quot;{{action_url}}&quot;>Open in {{app_name}}</a></p>"></textarea>
            </label>

            <label class="workspace-form-grid__wide">
                <span>Plain-text template</span>
                <textarea name="text_template" rows="6" maxlength="10000" required placeholder="Hello {{recipient_name}},&#10;&#10;{{message}}&#10;&#10;Open: {{action_url}}"></textarea>
            </label>

            <div class="workspace-form-grid__wide">
                <small>Available variables: <?= e(implode(', ', $variables)) ?>. Unknown variables are rejected.</small>
            </div>

            <div class="workspace-form-grid__wide">
                <button class="button button--primary" type="submit">Create inactive template</button>
            </div>
        </form>
    </section>

    <section class="workspace-table-section">
        <div class="workspace-table-head">
            <div>
                <h2>Template lifecycle</h2>
                <p>Only active templates override the normal notification-generated fallback email. Rich explicit emails such as Saved Search digests are not replaced.</p>
            </div>
        </div>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Notification type</th>
                        <th>Status</th>
                        <th>Subject</th>
                        <th>Updated</th>
                        <th>Updated by</th>
                        <th aria-label="Actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($templates === []): ?>
                        <tr><td colspan="7"><div class="workspace-record-empty">No email templates have been created yet.</div></td></tr>
                    <?php endif; ?>

                    <?php foreach ($templates as $template): ?>
                        <?php
                        $status = (string) $template['status'];
                        $tone = $status === 'active'
                            ? 'approved'
                            : ($status === 'inactive' ? 'pending' : 'neutral');
                        ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $template['name']) ?></strong>
                                <small><?= e((string) ($template['description'] ?? '')) ?></small>
                            </td>
                            <td>
                                <strong><?= e((string) ($supportedKeys[$template['template_key']] ?? $template['template_key'])) ?></strong>
                                <small><?= e((string) $template['template_key']) ?></small>
                            </td>
                            <td><span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= e((string) $template['subject_template']) ?></td>
                            <td><?= e((string) $template['updated_at']) ?></td>
                            <td><?= e((string) ($template['updated_by_name'] ?? 'System')) ?></td>
                            <td>
                                <details class="workspace-row-actions">
                                    <summary aria-label="Template actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>
                                    <div class="workspace-row-actions__menu">
                                        <?php if ($status === 'inactive'): ?>
                                            <form action="<?= e(url('/dashboard/email-templates/' . (int) $template['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="active">
                                                <button class="table-action table-action--primary" type="submit">Activate</button>
                                            </form>

                                            <details>
                                                <summary class="table-action">Edit template</summary>
                                                <form action="<?= e(url('/dashboard/email-templates/' . (int) $template['id'])) ?>" method="post" data-workspace-form class="workspace-inline-form">
                                                    <?= csrf_field() ?>
                                                    <select name="template_key" required>
                                                        <?php foreach ($supportedKeys as $key => $label): ?>
                                                            <option value="<?= e((string) $key) ?>" <?= (string) $template['template_key'] === (string) $key ? 'selected' : '' ?>>
                                                                <?= e((string) $label) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="text" name="name" maxlength="160" value="<?= e((string) $template['name']) ?>" required>
                                                    <input type="text" name="description" maxlength="2000" value="<?= e((string) ($template['description'] ?? '')) ?>">
                                                    <input type="text" name="subject_template" maxlength="255" value="<?= e((string) $template['subject_template']) ?>" required>
                                                    <textarea name="html_template" rows="8" maxlength="20000" required><?= e((string) $template['html_template']) ?></textarea>
                                                    <textarea name="text_template" rows="6" maxlength="10000" required><?= e((string) $template['text_template']) ?></textarea>
                                                    <button class="table-action table-action--primary" type="submit">Save changes</button>
                                                </form>
                                            </details>

                                            <form action="<?= e(url('/dashboard/email-templates/' . (int) $template['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="archived">
                                                <button class="table-action" type="submit">Archive</button>
                                            </form>
                                        <?php elseif ($status === 'active'): ?>
                                            <form action="<?= e(url('/dashboard/email-templates/' . (int) $template['id'] . '/status')) ?>" method="post" data-workspace-form>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="inactive">
                                                <button class="table-action" type="submit">Deactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="table-action-placeholder">Archived · no further changes</span>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
