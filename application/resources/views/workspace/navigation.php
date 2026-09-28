<?php

declare(strict_types=1);

$items = is_array($navigationItems ?? null) ? array_values($navigationItems) : [];
?>
<section class="workspace-page content-config-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel ?? 'Admin Control Center') ?></span>
            <h1>Navigation</h1>
            <p>Add, edit, reorder, enable, disable or delete public primary navigation items independently from Homepage customization.</p>
        </div>
        <button class="content-config-button content-config-button--primary" type="button" data-configure-open="navigation-create">
            <?= app_icon('plus') ?>
            <span>Add navigation</span>
        </button>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="content-config-table-wrap">
        <table class="content-config-table">
            <thead>
                <tr>
                    <th>Navigation item</th>
                    <th>Internal path</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th class="content-config-table__action">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items === []): ?>
                    <tr><td colspan="5"><div class="workspace-record-empty">No navigation items are configured.</div></td></tr>
                <?php endif; ?>

                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $id = (string) ($item['id'] ?? '');
                    $enabled = !empty($item['enabled']);
                    ?>
                    <tr>
                        <td>
                            <div class="content-config-feature">
                                <span class="content-config-feature__icon"><?= app_icon('menu') ?></span>
                                <span><strong><?= e((string) ($item['label'] ?? 'Untitled')) ?></strong><small><?= e((string) ($item['active_area'] ?? '') !== '' ? 'Active area: ' . (string) $item['active_area'] : 'Custom internal link') ?></small></span>
                            </div>
                        </td>
                        <td><code class="content-config-path"><?= e((string) ($item['path'] ?? '/')) ?></code></td>
                        <td><span class="content-config-position"><?= e((string) ($index + 1)) ?></span></td>
                        <td>
                            <form action="<?= e(url('/dashboard/navigation/' . rawurlencode($id) . '/toggle')) ?>" method="post" data-workspace-form data-toggle-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="enabled" value="<?= $enabled ? '1' : '0' ?>" data-toggle-value>
                                <label class="premium-switch">
                                    <input type="checkbox" <?= $enabled ? 'checked' : '' ?> data-auto-toggle aria-label="Toggle <?= e((string) ($item['label'] ?? 'navigation item')) ?>">
                                    <span class="premium-switch__track"><span></span></span>
                                    <b><?= $enabled ? 'ON' : 'OFF' ?></b>
                                </label>
                            </form>
                        </td>
                        <td class="content-config-table__action">
                            <button class="content-config-button" type="button" data-configure-open="navigation-config-<?= e($id) ?>">
                                <?= app_icon('settings') ?>
                                <span>Configure</span>
                            </button>
                        </td>
                    </tr>

                    <dialog class="config-dialog" id="navigation-config-<?= e($id) ?>" data-config-dialog>
                        <form class="config-dialog__panel" action="<?= e(url('/dashboard/navigation/' . rawurlencode($id))) ?>" method="post" data-workspace-form>
                            <?= csrf_field() ?>
                            <header class="config-dialog__head">
                                <div><span class="eyebrow">Primary Navigation</span><h2>Configure <?= e((string) ($item['label'] ?? 'item')) ?></h2></div>
                                <button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button>
                            </header>
                            <div class="config-dialog__body config-field-grid">
                                <label><span>Label</span><input class="form-control" name="label" maxlength="60" required value="<?= e((string) ($item['label'] ?? '')) ?>"></label>
                                <label><span>Internal path</span><input class="form-control" name="path" maxlength="255" required value="<?= e((string) ($item['path'] ?? '')) ?>" placeholder="/properties"></label>
                                <label><span>Position</span><select class="form-select" name="position"><?php for ($position = 1; $position <= max(1, count($items)); $position++): ?><option value="<?= $position ?>" <?= $position === $index + 1 ? 'selected' : '' ?>><?= $position ?></option><?php endfor; ?></select></label>
                                <label class="premium-inline-toggle"><input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>><span>Enabled</span></label>
                            </div>
                            <footer class="config-dialog__footer config-dialog__footer--split">
                                <button class="btn btn-danger table-action--danger" type="submit" formaction="<?= e(url('/dashboard/navigation/' . rawurlencode($id) . '/delete')) ?>">Delete</button>
                                <span><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit">Save navigation</button></span>
                            </footer>
                        </form>
                    </dialog>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <dialog class="config-dialog" id="navigation-create" data-config-dialog>
        <form class="config-dialog__panel" action="<?= e(url('/dashboard/navigation')) ?>" method="post" data-workspace-form>
            <?= csrf_field() ?>
            <header class="config-dialog__head">
                <div><span class="eyebrow">Primary Navigation</span><h2>Add navigation item</h2><p>Only safe same-site paths beginning with / are accepted.</p></div>
                <button type="button" data-configure-close aria-label="Close"><?= app_icon('plus') ?></button>
            </header>
            <div class="config-dialog__body config-field-grid">
                <label><span>Label</span><input class="form-control" name="label" maxlength="60" required placeholder="Resources"></label>
                <label><span>Internal path</span><input class="form-control" name="path" maxlength="255" required placeholder="/pages/resources"></label>
                <label><span>Position</span><select class="form-select" name="position"><?php for ($position = 1; $position <= count($items) + 1; $position++): ?><option value="<?= $position ?>" <?= $position === count($items) + 1 ? 'selected' : '' ?>><?= $position ?></option><?php endfor; ?></select></label>
                <label class="premium-inline-toggle"><input type="checkbox" name="enabled" value="1" checked><span>Enabled</span></label>
            </div>
            <footer class="config-dialog__footer"><button class="btn btn-outline-secondary" type="button" data-configure-close>Cancel</button><button class="btn btn-primary" type="submit"><?= app_icon('plus') ?> Add navigation</button></footer>
        </form>
    </dialog>
</section>
