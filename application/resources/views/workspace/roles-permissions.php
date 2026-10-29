<?php
$roles = is_array($roleGovernance['roles'] ?? null) ? $roleGovernance['roles'] : [];
$groups = is_array($roleGovernance['groups'] ?? null) ? $roleGovernance['groups'] : [];
$canManage = !empty($roleGovernance['can_manage']);
$saved = (string) ($saved ?? '');
$error = (string) ($error ?? '');
?>
<?= barama_css_once('admin') ?>
<section class="rbac-admin" data-rbac-admin>
    <header class="rbac-admin__hero">
        <div>
            <span class="rbac-admin__eyebrow">Users &amp; Security</span>
            <h1>Roles &amp; Permissions</h1>
            <p>Govern effective access across Barama using the canonical role and permission system.</p>
        </div>
        <div class="rbac-admin__authority">
            <?= app_icon('shield') ?>
            <span><strong><?= $canManage ? 'Super Administrator' : 'Read-only access' ?></strong><small><?= $canManage ? 'Permission governance enabled' : 'Super Administrator required to edit' ?></small></span>
        </div>
    </header>

    <?php if ($saved === '1'): ?>
        <div class="rbac-notice rbac-notice--success" role="status" data-rbac-notice><?= app_icon('check') ?><span><strong>Permissions updated</strong><small>The role permission assignment is now active across the platform.</small></span></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="rbac-notice rbac-notice--error" role="alert" data-rbac-notice><?= app_icon('alert') ?><span><strong>Could not save permissions</strong><small><?= e($error) ?></small></span></div>
    <?php endif; ?>

    <div class="rbac-stats" aria-label="Role governance summary">
        <div><span>System roles</span><strong><?= (int) ($roleGovernance['role_count'] ?? 0) ?></strong></div>
        <div><span>Platform permissions</span><strong><?= (int) ($roleGovernance['permission_count'] ?? 0) ?></strong></div>
        <div><span>Role assignments</span><strong><?= (int) ($roleGovernance['assignment_count'] ?? 0) ?></strong></div>
        <div><span>Governance</span><strong><?= $canManage ? 'Unlocked' : 'Protected' ?></strong></div>
    </div>

    <section class="rbac-panel">
        <div class="rbac-panel__head">
            <div><h2>System roles</h2><p>Each role uses the same permission records enforced by Barama routes and services.</p></div>
            <span class="rbac-panel__label">LIVE RBAC</span>
        </div>
        <div class="rbac-role-table" role="table" aria-label="System roles">
            <div class="rbac-role-table__header" role="row">
                <span>Role</span><span>Workspace</span><span>Members</span><span>Permissions</span><span>Actions</span>
            </div>
            <?php foreach ($roles as $role): ?>
                <div class="rbac-role-row" role="row">
                    <div class="rbac-role-identity">
                        <span class="rbac-role-icon"><?= app_icon((string) ($role['icon'] ?? 'users')) ?></span>
                        <span><strong><?= e((string) $role['name']) ?></strong><small><?= e((string) $role['description']) ?></small></span>
                    </div>
                    <div><span class="rbac-mobile-label">Workspace</span><strong><?= e((string) $role['scope']) ?></strong></div>
                    <div><span class="rbac-mobile-label">Members</span><strong><?= (int) $role['active_member_count'] ?></strong><small><?= (int) $role['member_count'] ?> total</small></div>
                    <div><span class="rbac-mobile-label">Permissions</span><strong><?= (int) $role['permission_count'] ?></strong><?php if (!empty($role['locked'])): ?><small>Full access</small><?php endif; ?></div>
                    <div class="rbac-role-actions">
                        <button type="button" class="rbac-btn" data-rbac-open="<?= e((string) $role['slug']) ?>"><?= !empty($role['locked']) ? 'View' : 'Configure' ?></button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="rbac-panel">
        <div class="rbac-panel__head">
            <div><h2>Platform permission matrix</h2><p>Effective permission coverage for every system role.</p></div>
            <span class="rbac-panel__label">EFFECTIVE ACCESS</span>
        </div>
        <div class="rbac-matrix-wrap">
            <table class="rbac-matrix">
                <thead><tr><th>Permission</th><?php foreach ($roles as $role): ?><th><?= e((string) $role['name']) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($groups as $group => $permissions): ?>
                    <tr class="rbac-matrix__group"><th colspan="<?= count($roles) + 1 ?>"><?= e((string) $group) ?></th></tr>
                    <?php foreach ($permissions as $permission): ?>
                        <tr>
                            <th><strong><?= e((string) $permission['name']) ?></strong><small><?= e((string) $permission['slug']) ?></small></th>
                            <?php foreach ($roles as $role): ?>
                                <?php $has = in_array((string) $permission['slug'], (array) $role['permissions'], true); ?>
                                <td><span class="rbac-matrix__state <?= $has ? 'is-on' : 'is-off' ?>" aria-label="<?= $has ? 'Allowed' : 'Not allowed' ?>"><?= $has ? '✓' : '—' ?></span></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php foreach ($roles as $role): ?>
        <div class="rbac-modal" data-rbac-modal="<?= e((string) $role['slug']) ?>" hidden>
            <button class="rbac-modal__backdrop" type="button" data-rbac-close aria-label="Close role configuration"></button>
            <section class="rbac-modal__card" role="dialog" aria-modal="true" aria-labelledby="rbac-title-<?= e((string) $role['slug']) ?>">
                <header class="rbac-modal__head">
                    <span class="rbac-role-icon"><?= app_icon((string) ($role['icon'] ?? 'users')) ?></span>
                    <div><h2 id="rbac-title-<?= e((string) $role['slug']) ?>"><?= e((string) $role['name']) ?></h2><p><?= e((string) $role['description']) ?></p></div>
                    <button type="button" class="rbac-modal__close" data-rbac-close aria-label="Close">×</button>
                </header>
                <?php if (!empty($role['locked'])): ?>
                    <div class="rbac-lock-note"><?= app_icon('shield') ?><span><strong>Immutable full access</strong><small>Super Administrator always receives every current platform permission and cannot be reduced from this screen.</small></span></div>
                <?php elseif (!$canManage): ?>
                    <div class="rbac-lock-note"><?= app_icon('lock') ?><span><strong>Read-only</strong><small>Only a Super Administrator can change system-role permissions.</small></span></div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/dashboard/roles-permissions/' . (string) $role['slug'])) ?>" class="rbac-modal__body">
                    <?= csrf_field() ?>
                    <?php foreach ($groups as $group => $permissions): ?>
                        <fieldset class="rbac-permission-group">
                            <legend><?= e((string) $group) ?></legend>
                            <?php foreach ($permissions as $permission): ?>
                                <?php
                                $slug = (string) $permission['slug'];
                                $checked = in_array($slug, (array) $role['permissions'], true);
                                $required = in_array($slug, (array) $role['required'], true);
                                $lockedPermission = !empty($role['locked']) || !$canManage || $slug === 'roles.manage';
                                ?>
                                <label class="rbac-permission-option">
                                    <input type="checkbox" name="permissions[]" value="<?= e($slug) ?>" <?= $checked ? 'checked' : '' ?> <?= $lockedPermission || $required ? 'disabled' : '' ?>>
                                    <?php if ($required && $checked): ?><input type="hidden" name="permissions[]" value="<?= e($slug) ?>"><?php endif; ?>
                                    <span><strong><?= e((string) $permission['name']) ?></strong><small><?= e((string) $permission['description']) ?></small></span>
                                    <?php if ($required): ?><em>Required</em><?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; ?>
                    <footer class="rbac-modal__actions">
                        <button type="button" class="rbac-btn rbac-btn--ghost" data-rbac-close>Close</button>
                        <?php if (!empty($role['editable'])): ?><button type="submit" class="rbac-btn rbac-btn--primary"><?= app_icon('check') ?> Save permissions</button><?php endif; ?>
                    </footer>
                </form>
            </section>
        </div>
    <?php endforeach; ?>
</section>
<?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="admin-roles-permissions"></script>
