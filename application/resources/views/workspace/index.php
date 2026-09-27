<?php

declare(strict_types=1);

$rows = is_array($records['rows'] ?? null) ? $records['rows'] : [];
$total = (int) ($records['total'] ?? 0);
$page = (int) ($records['page'] ?? 1);
$pages = (int) ($records['pages'] ?? 1);
$companies = is_array($formOptions['companies'] ?? null) ? $formOptions['companies'] : [];
$projects = is_array($formOptions['projects'] ?? null) ? $formOptions['projects'] : [];
$properties = is_array($formOptions['properties'] ?? null) ? $formOptions['properties'] : [];
$listings = is_array($formOptions['listings'] ?? null) ? $formOptions['listings'] : [];
$locations = is_array($formOptions['locations'] ?? null) ? $formOptions['locations'] : [];
$tableFilters = is_array($tableFilters ?? null) ? $tableFilters : [];
$filterOptions = is_array($filterOptions ?? null) ? $filterOptions : [];
$tableSort = trim((string) ($tableSort ?? 'updated_desc'));
$emptyState = is_array($emptyState ?? null) ? $emptyState : [];
$sectionGuide = is_array($sectionGuide ?? null) ? $sectionGuide : [];
$bulkMode = $workspaceType === 'admin' && $activeSection === 'approvals';
$queryBase = array_filter([
    'q' => $search,
    'status' => (string) ($tableFilters['status'] ?? ''),
    'type' => (string) ($tableFilters['type'] ?? ''),
    'location' => (string) ($tableFilters['location'] ?? ''),
    'sort' => $tableSort,
], static fn (string $value): bool => $value !== '');

$humanize = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', trim((string) $value)));

$currentUserId = (int) (auth_user()['id'] ?? 0);

$formatCell = static function (string $key, array $row) use ($humanize): string {
    $value = $row[$key] ?? null;

    if ($value === null || $value === '') {
        return '—';
    }

    if (in_array($key, ['price', 'budget_min', 'budget_max'], true)) {
        $currency = (string) ($row['currency'] ?? 'USD');
        return e($currency . ' ' . number_format((float) $value, 2));
    }

    if (in_array($key, [
        'usage_count',
        'children_count',
        'attachment_count',
        'primary_count',
        'company_count',
        'owned_company_count',
        'security_events_count',
        'projects_count',
        'published_projects_count',
        'properties_count',
        'approved_properties_count',
        'listings_count',
        'published_listings_count',
        'members_count',
    ], true)) {
        return e(number_format((int) $value));
    }

    if (in_array($key, ['original_name', 'target_title', 'parent_name', 'uploaded_by'], true)) {
        return e((string) $value);
    }

    if (in_array($key, ['readiness', 'marketplace_readiness'], true)) {
        $ready = strtolower((string) $value) === 'ready';
        return '<span class="status-badge status-badge--' . ($ready ? 'approved' : 'pending') . '">'
            . e($ready ? 'Ready' : 'Blocked')
            . '</span>';
    }

    if ($key === 'attachment_state') {
        $normalized = strtolower((string) $value) === 'attached' ? 'approved' : 'pending';
        return '<span class="status-badge status-badge--' . e($normalized) . '">'
            . e($humanize($value))
            . '</span>';
    }

    if ($key === 'size_bytes') {
        $bytes = (int) $value;
        if ($bytes >= 1048576) {
            return e(number_format($bytes / 1048576, 1) . ' MB');
        }

        return e(number_format(max(1, $bytes) / 1024, 1) . ' KB');
    }

    if ($key === 'width_px') {
        $height = $row['height_px'] ?? null;
        return e($height ? ((int) $value . ' × ' . (int) $height . ' px') : '—');
    }

    if ($key === 'is_primary') {
        return (int) $value === 1
            ? '<span class="status-badge status-badge--approved">Primary</span>'
            : '<span class="status-badge status-badge--neutral">Gallery</span>';
    }

    if ($key === 'email_state') {
        $normalized = strtolower((string) $value) === 'verified' ? 'approved' : 'pending';
        return '<span class="status-badge status-badge--' . e($normalized) . '">'
            . e($humanize($value))
            . '</span>';
    }

    if ($key === 'client_state') {
        $normalized = strtolower((string) $value) === 'hashed' ? 'approved' : 'neutral';
        return '<span class="status-badge status-badge--' . e($normalized) . '">'
            . e($humanize($value))
            . '</span>';
    }

    if ($key === 'roles') {
        return e(implode(', ', array_map(
            $humanize,
            array_filter(array_map('trim', explode(',', (string) $value)))
        )));
    }

    if ($key === 'status' || $key === 'stage') {
        $normalized = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)) ?: 'neutral';
        return '<span class="status-badge status-badge--' . e($normalized) . '">'
            . e($humanize($value))
            . '</span>';
    }

    if (
        str_ends_with($key, '_at')
        || in_array($key, ['updated_at', 'created_at', 'joined_at'], true)
    ) {
        $timestamp = strtotime((string) $value);
        return e($timestamp ? date('M j, Y H:i', $timestamp) : (string) $value);
    }

    return e($humanize($value));
};

$workflowUrl = static function (string $resource, int $id) use ($workspaceType): string {
    $base = $workspaceType === 'admin' ? '/dashboard/workflow/' : '/developer/workflow/';
    return url($base . rawurlencode($resource) . '/' . $id);
};

$actionButton = static function (
    string $resource,
    int $id,
    string $status,
    string $label,
    string $tone = 'secondary'
) use ($workflowUrl): string {
    return '<form action="' . e($workflowUrl($resource, $id)) . '" method="post" data-workspace-form>'
        . csrf_field()
        . '<input type="hidden" name="status" value="' . e($status) . '">'
        . '<button class="table-action table-action--' . e($tone) . '" type="submit">'
        . e($label)
        . '</button></form>';
};

$renderActions = static function (array $row) use (
    $workspaceType,
    $activeSection,
    $actionButton,
    $humanize,
    $currentUserId
): string {
    $id = (int) ($row['id'] ?? 0);

    if ($id < 1) {
        return '';
    }

    $status = (string) ($row['status'] ?? $row['stage'] ?? '');
    $buttons = [];

    $viewResource = $workspaceType === 'developer'
        ? match ($activeSection) {
            'overview', 'inquiries' => 'inquiry',
            'company' => 'company',
            'projects' => 'project',
            'properties' => 'property',
            'listings' => 'listing',
            'viewings' => 'viewing',
            'leads' => 'lead',
            default => null,
        }
        : match ($activeSection) {
            'overview', 'approvals' => (string) ($row['entity_type'] ?? ''),
            'companies' => 'company',
            'projects' => 'project',
            'properties' => 'property',
            'listings' => 'listing',
            'inquiries' => 'inquiry',
            'leads' => 'lead',
            'users' => 'user',
            'audit' => 'audit',
            default => null,
        };

    if (is_string($viewResource) && $viewResource !== '') {
        $base = $workspaceType === 'admin' ? '/dashboard/manage/' : '/developer/manage/';
        $viewLabel = $workspaceType === 'admin' && in_array($activeSection, ['overview', 'approvals'], true)
            ? 'Review'
            : 'View';
        $buttons[] = '<a class="table-action" href="' . e(url(
            $base . rawurlencode($viewResource) . '/' . $id
        )) . '">' . e($viewLabel) . '</a>';
    }

    if ($workspaceType === 'developer') {
        $resource = match ($activeSection) {
            'company' => 'company',
            'projects' => 'project',
            'properties' => 'property',
            'listings' => 'listing',
            'inquiries', 'overview' => 'inquiry',
            'leads' => 'lead',
            default => null,
        };

        if ($resource === 'company' || $resource === 'project' || $resource === 'property') {
            if ($status === 'draft') {
                $buttons[] = $actionButton($resource, $id, 'pending', 'Submit');
            } elseif ($status === 'rejected') {
                $buttons[] = $actionButton($resource, $id, 'draft', 'Return to draft');
            }
        } elseif ($resource === 'listing') {
            if ($status === 'draft') {
                $buttons[] = $actionButton('listing', $id, 'pending', 'Submit');
            } elseif (in_array($status, ['rejected', 'expired'], true)) {
                $buttons[] = $actionButton('listing', $id, 'draft', 'Return to draft');
            } elseif ($status === 'published') {
                $buttons[] = $actionButton('listing', $id, 'paused', 'Pause');
                $buttons[] = $actionButton('listing', $id, 'closed', 'Close', 'danger');
            } elseif ($status === 'paused') {
                $buttons[] = $actionButton('listing', $id, 'published', 'Resume', 'primary');
                $buttons[] = $actionButton('listing', $id, 'closed', 'Close', 'danger');
            }
        } elseif ($resource === 'inquiry') {
            if ($status === 'new') {
                $buttons[] = $actionButton('inquiry', $id, 'read', 'Mark read');
            } elseif ($status === 'read') {
                $buttons[] = $actionButton('inquiry', $id, 'replied', 'Mark replied');
            }

            if (
                in_array($status, ['new', 'read', 'replied'], true)
                && empty($row['converted_lead_id'])
            ) {
                $buttons[] = '<a class="table-action table-action--primary" href="' . e(url(
                    '/developer/manage/inquiry/' . $id
                )) . '">Review / convert lead</a>';
            } elseif (!empty($row['converted_lead_id'])) {
                $buttons[] = '<a class="table-action" href="' . e(url(
                    '/developer/manage/lead/' . (int) $row['converted_lead_id']
                )) . '">Open lead</a>';
            }
        } elseif ($resource === 'lead') {
            $next = match ($status) {
                'new' => ['contacted' => 'Contacted'],
                'contacted' => ['qualified' => 'Qualify'],
                'qualified' => ['viewing' => 'Viewing'],
                'viewing' => ['negotiation' => 'Negotiation'],
                'negotiation' => ['won' => 'Won', 'lost' => 'Lost'],
                'lost' => ['new' => 'Reopen'],
                default => [],
            };

            foreach ($next as $target => $label) {
                $buttons[] = $actionButton(
                    'lead',
                    $id,
                    $target,
                    $label,
                    $target === 'won' ? 'primary' : ($target === 'lost' ? 'danger' : 'secondary')
                );
            }
        } elseif ($activeSection === 'team') {
            $companyId = (int) ($row['company_id'] ?? 0);
            $rowType = (string) ($row['row_type'] ?? 'member');
            $memberRole = (string) ($row['member_role'] ?? '');
            $viewerRole = (string) ($row['viewer_role'] ?? '');

            if ($rowType === 'invitation' && (string) ($row['status'] ?? '') === 'pending') {
                $buttons[] = '<form action="' . e(url(
                    '/developer/companies/' . $companyId . '/invitations/' . $id . '/revoke'
                )) . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<button class="table-action table-action--danger" type="submit">Revoke invite</button></form>';
            } elseif (
                (string) ($row['status'] ?? '') === 'active'
                && $memberRole !== 'owner'
            ) {
                $assignable = $viewerRole === 'owner'
                    ? ['manager' => 'Manager', 'agent' => 'Agent', 'member' => 'Member']
                    : ($memberRole === 'manager'
                        ? []
                        : ['agent' => 'Agent', 'member' => 'Member']);

                foreach ($assignable as $targetRole => $label) {
                    if ($memberRole === $targetRole) {
                        continue;
                    }

                    $buttons[] = '<form action="' . e(url(
                        '/developer/companies/' . $companyId . '/members/' . $id . '/role'
                    )) . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<input type="hidden" name="member_role" value="' . e($targetRole) . '">'
                        . '<button class="table-action" type="submit">Set ' . e($label) . '</button></form>';
                }

                $buttons[] = '<form action="' . e(url(
                    '/developer/companies/' . $companyId . '/members/' . $id . '/deactivate'
                )) . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<button class="table-action table-action--danger" type="submit">Deactivate</button></form>';
            }
        } elseif ($activeSection === 'media') {
            $entityType = (string) ($row['entity_type'] ?? '');
            $entityId = (int) ($row['entity_id'] ?? 0);
            $targetStatus = (string) ($row['target_status'] ?? '');
            $editableMedia = in_array($targetStatus, ['draft', 'rejected'], true);

            if (
                in_array($entityType, ['company', 'project', 'property', 'listing'], true)
                && $entityId > 0
            ) {
                $buttons[] = '<a class="table-action" href="' . e(url(
                    '/developer/manage/' . rawurlencode($entityType) . '/' . $entityId
                )) . '">Open record</a>';
            }

            if ($editableMedia) {
                if (
                    empty($row['is_primary'])
                    && str_starts_with((string) ($row['mime_type'] ?? ''), 'image/')
                ) {
                    $buttons[] = '<form action="' . e(url(
                        '/developer/media/' . $id . '/primary'
                    )) . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<button class="table-action table-action--primary" type="submit">Make primary</button></form>';
                }

                $buttons[] = '<form action="' . e(url(
                    '/developer/media/' . $id . '/remove'
                )) . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<button class="table-action table-action--danger" type="submit">Remove attachment</button></form>';
            }
        }
    } else {
        if ($activeSection === 'users') {
            $roles = (string) ($row['roles'] ?? '');
            $isAdministrator = in_array(
                'administrator',
                array_map('trim', explode(',', strtolower($roles))),
                true
            );
            $isSelf = $id === $currentUserId;
            $emailVerified = (string) ($row['email_state'] ?? '') === 'verified';

            if (!$isSelf) {
                if ($status === 'active') {
                    $buttons[] = '<form action="' . e(url('/dashboard/users/' . $id . '/status'))
                        . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<input type="hidden" name="status" value="suspended">'
                        . '<button class="table-action" type="submit">Suspend</button></form>';
                    $buttons[] = '<form action="' . e(url('/dashboard/users/' . $id . '/status'))
                        . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<input type="hidden" name="status" value="deactivated">'
                        . '<button class="table-action table-action--danger" type="submit">Deactivate</button></form>';
                } else {
                    $buttons[] = '<form action="' . e(url('/dashboard/users/' . $id . '/status'))
                        . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<input type="hidden" name="status" value="active">'
                        . '<button class="table-action table-action--primary" type="submit">Restore</button></form>';
                }

                if ($isAdministrator) {
                    $buttons[] = '<form action="' . e(url('/dashboard/users/' . $id . '/administrator'))
                        . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<input type="hidden" name="enabled" value="0">'
                        . '<button class="table-action" type="submit">Remove admin</button></form>';
                } elseif ($status === 'active' && $emailVerified) {
                    $buttons[] = '<form action="' . e(url('/dashboard/users/' . $id . '/administrator'))
                        . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<input type="hidden" name="enabled" value="1">'
                        . '<button class="table-action" type="submit">Make admin</button></form>';
                }

                $buttons[] = '<form action="' . e(url('/dashboard/users/' . $id . '/sessions/revoke'))
                    . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<button class="table-action table-action--danger" type="submit">Revoke sessions</button></form>';
            }
        } elseif (in_array($activeSection, ['overview', 'approvals'], true)) {
            // Pending moderation is intentionally reviewed on the record page so
            // rejection reasons and workflow history are captured consistently.
        } elseif ($activeSection === 'companies') {
            if ($status === 'approved') {
                $buttons[] = $actionButton('company', $id, 'suspended', 'Suspend', 'danger');
            } elseif ($status === 'suspended') {
                $buttons[] = $actionButton('company', $id, 'approved', 'Restore', 'primary');
            }
        } elseif ($activeSection === 'projects') {
            if ($status === 'approved') {
                $buttons[] = $actionButton('project', $id, 'published', 'Publish', 'primary');
                $buttons[] = $actionButton('project', $id, 'archived', 'Archive', 'danger');
            } elseif ($status === 'published') {
                $buttons[] = $actionButton('project', $id, 'archived', 'Archive', 'danger');
            } elseif ($status === 'archived') {
                $buttons[] = $actionButton('project', $id, 'draft', 'Restore to draft');
            }
        } elseif ($activeSection === 'properties' && $status === 'approved') {
            $buttons[] = $actionButton('property', $id, 'archived', 'Archive', 'danger');
        } elseif ($activeSection === 'properties' && $status === 'archived') {
            $buttons[] = $actionButton('property', $id, 'draft', 'Restore to draft');
        } elseif ($activeSection === 'listings') {
            if ($status === 'published') {
                $buttons[] = $actionButton('listing', $id, 'paused', 'Pause');
                $buttons[] = $actionButton('listing', $id, 'closed', 'Close', 'danger');
            } elseif ($status === 'paused') {
                $buttons[] = $actionButton('listing', $id, 'published', 'Resume', 'primary');
                $buttons[] = $actionButton('listing', $id, 'closed', 'Close', 'danger');
            } elseif (in_array($status, ['rejected', 'expired'], true)) {
                $buttons[] = $actionButton('listing', $id, 'draft', 'Restore to draft');
            }
        } elseif ($activeSection === 'catalog') {
            $entityType = (string) ($row['entity_type'] ?? '');
            $usageCount = (int) ($row['usage_count'] ?? 0);
            $childrenCount = (int) ($row['children_count'] ?? 0);

            if (in_array($entityType, ['location', 'amenity'], true)) {
                $route = $entityType === 'location'
                    ? '/dashboard/locations/' . $id . '/status'
                    : '/dashboard/amenities/' . $id . '/status';

                if ($status === 'inactive') {
                    $buttons[] = '<form action="' . e(url($route)) . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<input type="hidden" name="status" value="active">'
                        . '<button class="table-action table-action--primary" type="submit">Activate</button></form>';
                } elseif ($status === 'active' && $usageCount === 0 && $childrenCount === 0) {
                    $buttons[] = '<form action="' . e(url($route)) . '" method="post" data-workspace-form>'
                        . csrf_field()
                        . '<input type="hidden" name="status" value="inactive">'
                        . '<button class="table-action table-action--danger" type="submit">Deactivate</button></form>';
                }
            }
        } elseif ($activeSection === 'media') {
            $attachmentCount = (int) ($row['attachment_count'] ?? 0);
            $targetType = (string) ($row['target_type'] ?? '');
            $targetId = (int) ($row['target_id'] ?? 0);

            if (
                $attachmentCount > 0
                && in_array($targetType, ['company', 'project', 'property', 'listing'], true)
                && $targetId > 0
            ) {
                $buttons[] = '<a class="table-action" href="' . e(url(
                    '/dashboard/manage/' . rawurlencode($targetType) . '/' . $targetId
                )) . '">Open attached record</a>';
            }

            if ($attachmentCount === 0) {
                $buttons[] = '<form action="' . e(url('/dashboard/media/' . $id . '/orphan'))
                    . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<button class="table-action table-action--danger" type="submit">Delete orphan</button></form>';
            }
        } elseif ($activeSection === 'inquiries') {
            if ($status === 'new') {
                $buttons[] = $actionButton('inquiry', $id, 'read', 'Mark read', 'primary');
                $buttons[] = $actionButton('inquiry', $id, 'spam', 'Mark spam', 'danger');
            } elseif ($status === 'read') {
                $buttons[] = $actionButton('inquiry', $id, 'replied', 'Mark replied', 'primary');
                $buttons[] = $actionButton('inquiry', $id, 'closed', 'Close');
                $buttons[] = $actionButton('inquiry', $id, 'spam', 'Mark spam', 'danger');
            } elseif ($status === 'replied') {
                $buttons[] = $actionButton('inquiry', $id, 'closed', 'Close');
            }

            if (!empty($row['converted_lead_id'])) {
                $buttons[] = '<a class="table-action" href="' . e(url(
                    '/dashboard/manage/lead/' . (int) $row['converted_lead_id']
                )) . '">Open linked lead</a>';
            }
        }
    }

    if ($buttons === []) {
        return '<span class="table-action-placeholder">No action</span>';
    }

    if (
        ($workspaceType === 'admin'
            && in_array($activeSection, ['approvals', 'companies', 'projects', 'properties', 'listings', 'inquiries', 'leads', 'catalog', 'media', 'users', 'audit'], true))
        || ($workspaceType === 'developer'
            && in_array(
                $activeSection,
                ['company', 'projects', 'properties', 'listings', 'inquiries', 'viewings', 'leads', 'team', 'media'],
                true
            ))
    ) {
        return '<details class="workspace-row-actions">'
            . '<summary aria-label="Record actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary>'
            . '<div class="workspace-row-actions__menu">' . implode('', $buttons) . '</div>'
            . '</details>';
    }

    return '<div class="table-actions">' . implode('', $buttons) . '</div>';
};
?>

<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel) ?></span>
            <h1><?= e($sectionTitle) ?></h1>
            <p><?= e($sectionDescription) ?></p>
        </div>

        <?php if ($workspaceType === 'developer' && in_array($activeSection, ['company', 'projects', 'properties', 'listings', 'team', 'media'], true)): ?>
            <span class="workspace-context-note">
                <?= app_icon('shield') ?>
                Company-scoped access enforced
            </span>
        <?php elseif ($workspaceType === 'admin' && in_array($activeSection, ['inquiries', 'leads'], true)): ?>
            <span class="workspace-context-note">
                <?= app_icon('shield') ?>
                Platform oversight · company-owned operations
            </span>
        <?php elseif ($workspaceType === 'admin' && in_array($activeSection, ['catalog', 'media'], true)): ?>
            <span class="workspace-context-note">
                <?= app_icon('shield') ?>
                Canonical real-estate platform data
            </span>
        <?php elseif ($workspaceType === 'admin' && $activeSection === 'users'): ?>
            <span class="workspace-context-note">
                <?= app_icon('shield') ?>
                Account security · real-estate access context
            </span>
        <?php elseif ($workspaceType === 'admin' && $activeSection === 'audit'): ?>
            <span class="workspace-context-note">
                <?= app_icon('lock') ?>
                Immutable security events
            </span>
        <?php elseif ($workspaceType === 'admin'): ?>
            <span class="workspace-context-note">
                <?= app_icon('shield') ?>
                Administrator controls
            </span>
        <?php endif; ?>
    </div>

    <div class="workspace-stats" aria-label="Workspace summary">
        <?php foreach ($stats as $stat): ?>
            <?php if (!empty($stat['href'])): ?>
                <a class="workspace-stat workspace-stat--link" href="<?= e(url((string) $stat['href'])) ?>">
                    <span class="workspace-stat__icon"><?= app_icon((string) $stat['icon']) ?></span>
                    <div>
                        <strong><?= e((string) $stat['value']) ?></strong>
                        <span><?= e((string) $stat['label']) ?></span>
                    </div>
                </a>
            <?php else: ?>
                <article class="workspace-stat">
                    <span class="workspace-stat__icon"><?= app_icon((string) $stat['icon']) ?></span>
                    <div>
                        <strong><?= e((string) $stat['value']) ?></strong>
                        <span><?= e((string) $stat['label']) ?></span>
                    </div>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($sectionGuide['steps'])): ?>
        <section
            class="workspace-portfolio-lifecycle"
            aria-label="<?= e((string) ($sectionGuide['aria_label'] ?? 'Workspace workflow')) ?>"
        >
            <div class="workspace-portfolio-lifecycle__head">
                <span><?= app_icon('shield') ?></span>
                <div>
                    <strong><?= e((string) ($sectionGuide['title'] ?? 'Workspace workflow')) ?></strong>
                    <small><?= e((string) ($sectionGuide['subtitle'] ?? 'Canonical workflow for this module')) ?></small>
                </div>
            </div>
            <ol>
                <?php foreach ((array) $sectionGuide['steps'] as $index => $step): ?>
                    <li><span><?= e((string) ($index + 1)) ?></span><strong><?= e((string) $step) ?></strong></li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($sectionGuide['note'])): ?><p><?= e((string) $sectionGuide['note']) ?></p><?php endif; ?>
        </section>
    <?php endif; ?>

    <div data-workspace-notice hidden></div>

    <?php if ($workspaceType === 'developer' && $activeSection === 'company'): ?>
        <details class="workspace-create">
            <summary><?= app_icon('plus') ?><span>Add company</span></summary>
            <form action="<?= e(url('/developer/companies')) ?>" method="post" data-workspace-form class="workspace-form">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label><span>Company name</span><input class="form-control" name="name" required maxlength="180"></label>
                    <label><span>Company type</span>
                        <select class="form-select" name="company_type" required>
                            <option value="developer">Developer</option>
                            <option value="agency">Agency</option>
                            <option value="property_company">Property company</option>
                            <option value="owner">Owner</option>
                        </select>
                    </label>
                    <label><span>Email</span><input class="form-control" name="email" type="email" maxlength="191"></label>
                    <label><span>Phone</span><input class="form-control" name="phone" maxlength="60"></label>
                    <label class="workspace-form__span-2"><span>Location</span>
                        <select class="form-select" name="location_id">
                            <option value="">Select location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= e((string) $location['id']) ?>">
                                    <?= e((string) $location['name']) ?> · <?= e($humanize($location['type'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Create company</button>
            </form>
        </details>
    <?php elseif ($workspaceType === 'developer' && $activeSection === 'projects'): ?>
        <details class="workspace-create">
            <summary><?= app_icon('plus') ?><span>Add project</span></summary>
            <form action="<?= e(url('/developer/projects')) ?>" method="post" data-workspace-form class="workspace-form">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label><span>Company</span>
                        <select class="form-select" name="company_id" required>
                            <option value="">Select company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= e((string) $company['id']) ?>"><?= e((string) $company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Project name</span><input class="form-control" name="name" required maxlength="191"></label>
                    <label><span>Project type</span>
                        <select class="form-select" name="project_type" required>
                            <option value="residential">Residential</option>
                            <option value="commercial">Commercial</option>
                            <option value="mixed_use">Mixed use</option>
                            <option value="land_development">Land development</option>
                            <option value="industrial">Industrial</option>
                            <option value="hospitality">Hospitality</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label><span>Completion</span>
                        <select class="form-select" name="completion_status" required>
                            <option value="planned">Planned</option>
                            <option value="pre_launch">Pre-launch</option>
                            <option value="under_construction">Under construction</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On hold</option>
                        </select>
                    </label>
                    <label class="workspace-form__span-2"><span>Location</span>
                        <select class="form-select" name="location_id">
                            <option value="">Select location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Create project</button>
            </form>
        </details>
    <?php elseif ($workspaceType === 'developer' && $activeSection === 'properties'): ?>
        <details class="workspace-create">
            <summary><?= app_icon('plus') ?><span>Add property</span></summary>
            <form action="<?= e(url('/developer/properties')) ?>" method="post" data-workspace-form class="workspace-form">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label><span>Company</span>
                        <select class="form-select" name="company_id" required>
                            <option value="">Select company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= e((string) $company['id']) ?>"><?= e((string) $company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Title</span><input class="form-control" name="title" required maxlength="191"></label>
                    <label><span>Property type</span>
                        <select class="form-select" name="property_type" required>
                            <?php foreach (['apartment','condominium','house','villa','townhouse','land','office','retail','warehouse','industrial','hotel','commercial','other'] as $type): ?>
                                <option value="<?= e($type) ?>"><?= e($humanize($type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Project</span>
                        <select class="form-select" name="project_id">
                            <option value="">Standalone property</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= e((string) $project['id']) ?>"><?= e((string) $project['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Bedrooms</span><input class="form-control" name="bedrooms" type="number" min="0" step="0.5"></label>
                    <label><span>Bathrooms</span><input class="form-control" name="bathrooms" type="number" min="0" step="0.5"></label>
                    <label><span>Area</span><input class="form-control" name="area_value" type="number" min="0" step="0.01"></label>
                    <label><span>Area unit</span><input class="form-control" name="area_unit" value="sqm" maxlength="20"></label>
                </div>
                <button class="btn btn-primary" type="submit">Create property</button>
            </form>
        </details>
    <?php elseif ($workspaceType === 'developer' && $activeSection === 'listings'): ?>
        <details class="workspace-create">
            <summary><?= app_icon('plus') ?><span>Add listing</span></summary>
            <form action="<?= e(url('/developer/listings')) ?>" method="post" data-workspace-form class="workspace-form">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label class="workspace-form__span-2"><span>Property</span>
                        <select class="form-select" name="property_id" required>
                            <option value="">Select property</option>
                            <?php foreach ($properties as $property): ?>
                                <option value="<?= e((string) $property['id']) ?>">
                                    <?= e((string) $property['reference_code']) ?> · <?= e((string) $property['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Offer</span>
                        <select class="form-select" name="transaction_type" required>
                            <option value="sale">For sale</option>
                            <option value="rent">For rent</option>
                        </select>
                    </label>
                    <label><span>Price</span><input class="form-control" name="price" type="number" min="0" step="0.01" required></label>
                    <label><span>Currency</span><input class="form-control" name="currency" value="<?= e((string) (($workspacePreferences['default_currency'] ?? 'USD'))) ?>" maxlength="3" required></label>
                    <label><span>Pricing period</span>
                        <select class="form-select" name="pricing_period">
                            <option value="">Not applicable</option>
                            <option value="month">Monthly</option>
                            <option value="week">Weekly</option>
                            <option value="day">Daily</option>
                            <option value="year">Yearly</option>
                        </select>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Create listing</button>
            </form>
        </details>
    <?php elseif ($workspaceType === 'developer' && $activeSection === 'team'): ?>
        <details class="workspace-create">
            <summary><?= app_icon('plus') ?><span>Invite team member</span></summary>
            <form action="<?= e(url('/developer/companies/' . (int) ($companies[0]['id'] ?? 0) . '/members')) ?>" method="post" data-workspace-form data-company-action class="workspace-form">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label><span>Company</span>
                        <select class="form-select" name="company_selector" data-company-selector required>
                            <option value="">Select company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= e((string) $company['id']) ?>"><?= e((string) $company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>User email</span><input class="form-control" name="email" type="email" required maxlength="191"></label>
                    <label><span>Role</span>
                        <select class="form-select" name="member_role" required>
                            <option value="manager">Manager</option>
                            <option value="agent">Agent</option>
                            <option value="member">Member</option>
                        </select>
                    </label>
                </div>
                <button class="btn btn-primary" type="submit">Send invitation</button>
            </form>
        </details>
    <?php elseif ($workspaceType === 'developer' && $activeSection === 'media'): ?>
        <details class="workspace-create">
            <summary><?= app_icon('plus') ?><span>Attach media to listing inventory</span></summary>
            <form action="<?= e(url('/developer/media')) ?>" method="post" enctype="multipart/form-data" data-workspace-form class="workspace-form">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label class="workspace-form__span-2"><span>Real-estate record</span>
                        <select class="form-select" name="media_target" required>
                            <option value="">Select an editable record</option>

                            <?php $editableCompanies = array_filter($companies, static fn (array $item): bool => in_array((string) ($item['status'] ?? ''), ['draft','rejected'], true)); ?>
                            <?php if ($editableCompanies !== []): ?>
                                <optgroup label="Companies">
                                    <?php foreach ($editableCompanies as $company): ?>
                                        <option value="company:<?= e((string) $company['id']) ?>"><?= e((string) $company['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>

                            <?php $editableProjects = array_filter($projects, static fn (array $item): bool => in_array((string) ($item['status'] ?? ''), ['draft','rejected'], true)); ?>
                            <?php if ($editableProjects !== []): ?>
                                <optgroup label="Projects">
                                    <?php foreach ($editableProjects as $project): ?>
                                        <option value="project:<?= e((string) $project['id']) ?>"><?= e((string) $project['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>

                            <?php $editableProperties = array_filter($properties, static fn (array $item): bool => in_array((string) ($item['status'] ?? ''), ['draft','rejected'], true)); ?>
                            <?php if ($editableProperties !== []): ?>
                                <optgroup label="Properties">
                                    <?php foreach ($editableProperties as $property): ?>
                                        <option value="property:<?= e((string) $property['id']) ?>"><?= e((string) $property['reference_code']) ?> · <?= e((string) $property['title']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>

                            <?php $editableListings = array_filter($listings, static fn (array $item): bool => in_array((string) ($item['status'] ?? ''), ['draft','rejected'], true)); ?>
                            <?php if ($editableListings !== []): ?>
                                <optgroup label="Listings">
                                    <?php foreach ($editableListings as $listing): ?>
                                        <option value="listing:<?= e((string) $listing['id']) ?>"><?= e((string) $listing['reference_code']) ?> · <?= e((string) $listing['property_title']) ?> · <?= e($humanize($listing['transaction_type'])) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </label>
                    <label class="workspace-form__span-2"><span>File</span><input class="form-control" name="file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
                    <label class="workspace-form__span-2"><span>Alt text</span><input class="form-control" name="alt_text" maxlength="255" placeholder="Describe the property image for accessibility and listing context"></label>
                    <label><span>Collection</span>
                        <select class="form-select" name="collection">
                            <option value="gallery">Gallery</option>
                            <option value="floorplan">Floor plan</option>
                            <option value="document">Document</option>
                        </select>
                    </label>
                    <label class="workspace-check"><input type="checkbox" name="is_primary" value="1"><span>Use as primary listing image</span></label>
                </div>
                <button class="btn btn-primary" type="submit"><?= app_icon('image') ?> Upload & attach media</button>
            </form>
        </details>
    <?php elseif ($workspaceType === 'admin' && $activeSection === 'catalog'): ?>
        <div class="workspace-form-pair">
            <details class="workspace-create">
                <summary><?= app_icon('plus') ?><span>Add location</span></summary>
                <form action="<?= e(url('/dashboard/locations')) ?>" method="post" data-workspace-form class="workspace-form">
                    <?= csrf_field() ?>
                    <div class="workspace-form__grid workspace-form__grid--single">
                        <label><span>Name</span><input class="form-control" name="name" required maxlength="160"></label>
                        <label><span>Type</span>
                            <select class="form-select" name="type" required>
                                <?php foreach (['country','province','state','city','district','commune','neighborhood'] as $type): ?>
                                    <option value="<?= e($type) ?>"><?= e($humanize($type)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span>Parent</span>
                            <select class="form-select" name="parent_id">
                                <option value="">No parent</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span>Country code</span><input class="form-control" name="country_code" maxlength="2" placeholder="KH"></label>
                        <label><span>Latitude</span><input class="form-control" name="latitude" type="number" step="0.0000001" min="-90" max="90"></label>
                        <label><span>Longitude</span><input class="form-control" name="longitude" type="number" step="0.0000001" min="-180" max="180"></label>
                        <label><span>Sort order</span><input class="form-control" name="sort_order" type="number" value="0"></label>
                    </div>
                    <button class="btn btn-primary" type="submit">Create location</button>
                </form>
            </details>
            <details class="workspace-create">
                <summary><?= app_icon('plus') ?><span>Add amenity</span></summary>
                <form action="<?= e(url('/dashboard/amenities')) ?>" method="post" data-workspace-form class="workspace-form">
                    <?= csrf_field() ?>
                    <div class="workspace-form__grid workspace-form__grid--single">
                        <label><span>Name</span><input class="form-control" name="name" required maxlength="140"></label>
                        <label><span>Category</span><input class="form-control" name="category" value="general" maxlength="80"></label>
                        <label><span>Icon key</span><input class="form-control" name="icon_key" maxlength="100"></label>
                        <label><span>Sort order</span><input class="form-control" name="sort_order" type="number" value="0"></label>
                    </div>
                    <button class="btn btn-primary" type="submit">Create amenity</button>
                </form>
            </details>
        </div>
    <?php endif; ?>

    <?php if ($workspaceType === 'admin' && $activeSection === 'catalog'): ?>
        <section class="workspace-management-guide workspace-management-guide--media" aria-label="Catalog safeguards">
            <div><span><?= app_icon('map') ?></span><strong>Location hierarchy</strong><small>Parent and child locations remain explicit so marketplace inventory uses one canonical geography.</small></div>
            <div><span><?= app_icon('shield') ?></span><strong>In-use protection</strong><small>Locations or amenities referenced by active real-estate data cannot be deactivated.</small></div>
            <div><span><?= app_icon('check') ?></span><strong>Status, not deletion</strong><small>Catalog records are activated or deactivated without destroying historical real-estate references.</small></div>
        </section>
    <?php elseif ($workspaceType === 'admin' && $activeSection === 'media'): ?>
        <section class="workspace-management-guide workspace-management-guide--media" aria-label="Platform media safeguards">
            <div><span><?= app_icon('image') ?></span><strong>Attachment context</strong><small>Every attached asset shows the first real-estate record, attachment count and primary-image usage.</small></div>
            <div><span><?= app_icon('shield') ?></span><strong>Owned media protected</strong><small>Attached assets stay under their Company, Project, Property or Listing workflow.</small></div>
            <div><span><?= app_icon('alert') ?></span><strong>Orphan cleanup only</strong><small>Admin deletion is limited to assets with zero real-estate attachments.</small></div>
        </section>
    <?php endif; ?>

    <?php if ($workspaceType === 'developer' && $activeSection === 'team'): ?>
        <section class="workspace-management-guide" aria-label="Company team roles">
            <div><span><?= app_icon('shield') ?></span><strong>Owner</strong><small>Company ownership and manager-role control.</small></div>
            <div><span><?= app_icon('users') ?></span><strong>Manager</strong><small>Manages listing operations and non-manager team members.</small></div>
            <div><span><?= app_icon('user') ?></span><strong>Agent</strong><small>Works with property listings, inquiries, viewings and leads as permitted.</small></div>
            <div><span><?= app_icon('home') ?></span><strong>Member</strong><small>Company workspace access without ownership controls.</small></div>
        </section>
    <?php elseif ($workspaceType === 'developer' && $activeSection === 'media'): ?>
        <section class="workspace-management-guide workspace-management-guide--media" aria-label="Listing media rules">
            <div><span><?= app_icon('image') ?></span><strong>Listing media</strong><small>Images and PDFs stay attached to a company, project, property or listing record.</small></div>
            <div><span><?= app_icon('shield') ?></span><strong>Workflow locked</strong><small>Reviewed or published records keep their approved media unchanged until returned to an editable state.</small></div>
            <div><span><?= app_icon('check') ?></span><strong>Primary image</strong><small>One primary image can lead each gallery collection.</small></div>
        </section>
    <?php endif; ?>

    <?php if ($workspaceType === 'admin' && $activeSection === 'users'): ?>
        <section class="workspace-management-guide" aria-label="User administration safeguards">
            <div><span><?= app_icon('shield') ?></span><strong>Administrator continuity</strong><small>The final active administrator cannot be disabled or stripped of admin access.</small></div>
            <div><span><?= app_icon('building') ?></span><strong>Company ownership</strong><small>Sole company owners must transfer ownership before their account can be suspended or deactivated.</small></div>
            <div><span><?= app_icon('lock') ?></span><strong>Session invalidation</strong><small>Status, administrator-role and explicit session actions invalidate existing authenticated sessions.</small></div>
        </section>
    <?php elseif ($workspaceType === 'admin' && $activeSection === 'audit'): ?>
        <section class="workspace-management-guide" aria-label="Security audit rules">
            <div><span><?= app_icon('shield') ?></span><strong>Immutable evidence</strong><small>Audit events are read-only in the administrator interface.</small></div>
            <div><span><?= app_icon('lock') ?></span><strong>Hashed client data</strong><small>IP and user-agent values are stored only as SHA-256 fingerprints, not raw client values.</small></div>
            <div><span><?= app_icon('search') ?></span><strong>Traceability</strong><small>Filter by event family and inspect actor, subject and safe metadata for account-security investigations.</small></div>
        </section>
    <?php endif; ?>

    <section class="workspace-panel">
        <div class="workspace-panel__toolbar">
            <div>
                <strong><?= e($activeSection === 'overview' ? 'Recent activity' : $sectionTitle) ?></strong>
                <span><?= e(number_format($total)) ?> record<?= $total === 1 ? '' : 's' ?></span>
            </div>

            <form method="get" class="workspace-table-controls" role="search">
                <div class="workspace-search">
                    <span><?= app_icon('search') ?></span>
                    <input name="q" value="<?= e($search) ?>" placeholder="Search records" maxlength="80" aria-label="Search records">
                </div>

                <?php if (($filterOptions['statuses'] ?? []) !== []): ?>
                    <select name="status" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <?php foreach ($filterOptions['statuses'] as $option): ?>
                            <option value="<?= e((string) $option) ?>" <?= (string) ($tableFilters['status'] ?? '') === (string) $option ? 'selected' : '' ?>>
                                <?= e($humanize($option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php if (($filterOptions['types'] ?? []) !== []): ?>
                    <select name="type" aria-label="Filter by type">
                        <option value="">All types</option>
                        <?php foreach ($filterOptions['types'] as $option): ?>
                            <option value="<?= e((string) $option) ?>" <?= (string) ($tableFilters['type'] ?? '') === (string) $option ? 'selected' : '' ?>>
                                <?= e($humanize($option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php if (!empty($filterOptions['location'])): ?>
                    <select name="location" aria-label="Filter by location">
                        <option value="">All locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= e((string) $location['id']) ?>" <?= (string) ($tableFilters['location'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>>
                                <?= e((string) $location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php if (($filterOptions['sorts'] ?? []) !== []): ?>
                    <select name="sort" aria-label="Sort records">
                        <?php foreach ($filterOptions['sorts'] as $value => $label): ?>
                            <option value="<?= e((string) $value) ?>" <?= $tableSort === (string) $value ? 'selected' : '' ?>>
                                <?= e((string) $label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <button type="submit">Apply</button>
                <?php if ($queryBase !== []): ?>
                    <a href="<?= e(url($workspaceType === 'admin'
                        ? ($activeSection === 'overview' ? '/dashboard' : '/dashboard/' . $activeSection)
                        : ($activeSection === 'overview' ? '/developer' : '/developer/' . $activeSection))) ?>">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($bulkMode && $rows !== []): ?>
            <div class="workspace-bulk-bar">
                <form id="bulk-moderation-form" action="<?= e(url('/dashboard/bulk-workflow')) ?>" method="post" data-workspace-form data-bulk-form>
                    <?= csrf_field() ?>
                    <span><strong data-bulk-count>0</strong> selected</span>
                    <select name="decision" aria-label="Bulk moderation action" required>
                        <option value="approve">Approve selected</option>
                        <option value="reject">Reject selected</option>
                    </select>
                    <input name="note" maxlength="500" placeholder="Decision note · required when rejecting">
                    <button type="submit" disabled data-bulk-submit>Apply action</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="workspace-table-wrap">
            <table class="workspace-table">
                <thead>
                    <tr>
                        <?php if ($bulkMode): ?>
                            <th scope="col" class="workspace-table__check">
                                <input type="checkbox" data-bulk-toggle aria-label="Select all records on this page">
                            </th>
                        <?php endif; ?>
                        <?php foreach ($columns as $label): ?>
                            <th scope="col"><?= e($label) ?></th>
                        <?php endforeach; ?>
                        <th scope="col" class="workspace-table__actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="<?= e((string) (count($columns) + 1 + ($bulkMode ? 1 : 0))) ?>" class="workspace-empty workspace-empty--contextual">
                                <span class="workspace-empty__icon"><?= app_icon((string) ($emptyState['icon'] ?? 'search')) ?></span>
                                <strong><?= e((string) ($emptyState['title'] ?? 'No records found')) ?></strong>
                                <span><?= e((string) ($emptyState['detail'] ?? 'Records matching this section and search will appear here.')) ?></span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php if ($bulkMode): ?>
                                    <td class="workspace-table__check">
                                        <input
                                            type="checkbox"
                                            name="records[]"
                                            value="<?= e((string) ($row['entity_type'] ?? '')) ?>:<?= e((string) ($row['id'] ?? '')) ?>"
                                            form="bulk-moderation-form"
                                            data-bulk-item
                                            aria-label="Select <?= e((string) ($row['title'] ?? 'record')) ?>"
                                        >
                                    </td>
                                <?php endif; ?>
                                <?php foreach (array_keys($columns) as $key): ?>
                                    <td><?= $formatCell($key, $row) ?></td>
                                <?php endforeach; ?>
                                <td class="workspace-table__actions"><?= $renderActions($row) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="workspace-pagination" aria-label="Table pagination">
                <span>Page <?= e((string) $page) ?> of <?= e((string) $pages) ?></span>
                <form method="get" class="workspace-pagination__goto" aria-label="Go to page">
                    <?php foreach ($queryBase as $queryKey => $queryValue): ?>
                        <input type="hidden" name="<?= e((string) $queryKey) ?>" value="<?= e((string) $queryValue) ?>">
                    <?php endforeach; ?>
                    <label for="workspace-page-jump">Go to page</label>
                    <input id="workspace-page-jump" name="page" type="number" min="1" max="<?= e((string) $pages) ?>" value="<?= e((string) $page) ?>" inputmode="numeric">
                    <button type="submit">Go</button>
                </form>
                <div class="workspace-pagination__steps">
                    <?php if ($page > 1): ?>
                        <?php $previousQuery = $queryBase; $previousQuery['page'] = $page - 1; ?>
                        <a href="?<?= e(http_build_query($previousQuery)) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                        <?php $nextQuery = $queryBase; $nextQuery['page'] = $page + 1; ?>
                        <a href="?<?= e(http_build_query($nextQuery)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</section>
