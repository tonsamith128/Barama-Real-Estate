<?php

declare(strict_types=1);

$companies = is_array($formOptions['companies'] ?? null) ? $formOptions['companies'] : [];
$projects = is_array($formOptions['projects'] ?? null) ? $formOptions['projects'] : [];
$properties = is_array($formOptions['properties'] ?? null) ? $formOptions['properties'] : [];
$locations = is_array($formOptions['locations'] ?? null) ? $formOptions['locations'] : [];

$humanize = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', trim((string) $value)));

$currentUserId = (int) (auth_user()['id'] ?? 0);

$title = (string) (
    $record['name']
    ?? $record['title']
    ?? $record['original_name']
    ?? $record['property_title']
    ?? $record['contact_name']
    ?? $record['event_type']
    ?? ucfirst($recordResource)
);
$status = (string) ($record['status'] ?? $record['stage'] ?? '');
$auditMetadata = [];
if ($recordResource === 'audit' && !empty($record['metadata_json'])) {
    $decodedMetadata = json_decode((string) $record['metadata_json'], true);
    $auditMetadata = is_array($decodedMetadata) ? $decodedMetadata : [];
}
$viewingTransitions = $recordResource === 'viewing'
    ? match ($status) {
        'pending' => ['confirmed' => 'Confirm', 'rescheduled' => 'Reschedule', 'declined' => 'Decline'],
        'confirmed' => ['rescheduled' => 'Reschedule', 'completed' => 'Complete', 'declined' => 'Decline'],
        'rescheduled' => ['confirmed' => 'Confirm', 'rescheduled' => 'Reschedule', 'completed' => 'Complete', 'declined' => 'Decline'],
        default => [],
    }
    : [];
$scheduledLocal = !empty($record['scheduled_for'])
    ? date('Y-m-d\TH:i', strtotime((string) $record['scheduled_for']))
    : '';
$adminEditMode = !empty($adminEditMode);
$editable = (
        $workspaceType === 'developer'
        && in_array($recordResource, ['company', 'project', 'property', 'listing'], true)
        && in_array($status, ['draft', 'rejected'], true)
    )
    || (
        $workspaceType === 'admin'
        && in_array($recordResource, ['company', 'project', 'property'], true)
        && $adminEditMode
    );

$moderationBlockers = [];

if ($workspaceType === 'admin') {
    if ($recordResource === 'project') {
        if ((string) ($record['company_status'] ?? '') !== 'approved') {
            $moderationBlockers[] = 'The parent company must be approved first.';
        }
    } elseif ($recordResource === 'property') {
        if ((string) ($record['company_status'] ?? '') !== 'approved') {
            $moderationBlockers[] = 'The parent company must be approved first.';
        }

        if (
            !empty($record['project_id'])
            && !in_array(
                (string) ($record['project_status'] ?? ''),
                ['approved', 'published'],
                true
            )
        ) {
            $moderationBlockers[] = 'The linked project must be approved first.';
        }
    } elseif ($recordResource === 'listing') {
        if ((string) ($record['property_status'] ?? '') !== 'approved') {
            $moderationBlockers[] = 'The parent property must be approved first.';
        }

        if ((string) ($record['company_status'] ?? '') !== 'approved') {
            $moderationBlockers[] = 'The parent company must currently be approved.';
        }

        if (
            !empty($record['project_id'])
            && !in_array(
                (string) ($record['project_status'] ?? ''),
                ['approved', 'published'],
                true
            )
        ) {
            $moderationBlockers[] = 'The linked project must currently be approved.';
        }
    }
}

$moderationReady = $moderationBlockers === [];

$propertyReadiness = null;
if ($recordResource === 'property') {
    $propertyReadiness = (new \Barama\Services\PropertyReadinessService())->evaluate(
        $record,
        is_array($media ?? null) ? $media : []
    );

    if ($workspaceType === 'admin' && $status === 'pending' && empty($propertyReadiness['approval_ready'])) {
        foreach ((array) ($propertyReadiness['missing'] ?? []) as $readinessCheck) {
            $readinessKey = (string) ($readinessCheck['key'] ?? '');
            if (in_array($readinessKey, ['company_dependency', 'project_dependency'], true)) {
                continue;
            }
            $message = trim((string) ($readinessCheck['message'] ?? ''));
            if ($message !== '') {
                $moderationBlockers[] = $message;
            }
        }
        $moderationReady = $moderationBlockers === [];
    }
}

$mediaPublic = match ($recordResource) {
    'company' => $status === 'approved',
    'project' => $status === 'published',
    'property' => $status === 'approved',
    'listing' => $status === 'published',
    default => false,
};

$sectionPath = match ($recordResource) {
    'company' => $workspaceType === 'admin' ? '/dashboard/companies' : '/developer/company',
    'project' => $workspaceType === 'admin' ? '/dashboard/projects' : '/developer/projects',
    'property' => $workspaceType === 'admin' ? '/dashboard/properties' : '/developer/properties',
    'listing' => $workspaceType === 'admin' ? '/dashboard/listings' : '/developer/listings',
    'inquiry' => $workspaceType === 'admin' ? '/dashboard/inquiries' : '/developer/inquiries',
    'viewing' => $workspaceType === 'admin' ? '/dashboard/viewings' : '/developer/viewings',
    'lead' => $workspaceType === 'admin' ? '/dashboard/leads' : '/developer/leads',
    'media' => '/dashboard/media',
    'page' => '/dashboard/pages',
    'user' => '/dashboard/users',
    'audit' => '/dashboard/audit',
    default => $workspaceType === 'admin' ? '/dashboard' : '/developer',
};

$workflowBase = $workspaceType === 'admin' ? '/dashboard/workflow/' : '/developer/workflow/';
$workflowButton = static function (
    string $target,
    string $label,
    string $tone = 'secondary'
) use ($recordResource, $record, $workflowBase): string {
    return '<form action="' . e(url(
        $workflowBase . rawurlencode($recordResource) . '/' . (int) $record['id']
    )) . '" method="post" data-workspace-form>'
        . csrf_field()
        . '<input type="hidden" name="status" value="' . e($target) . '">'
        . '<button class="btn btn-sm '
        . ($tone === 'primary' ? 'btn-primary' : ($tone === 'danger' ? 'btn-outline-danger' : 'btn-outline-secondary'))
        . '" type="submit">' . e($label) . '</button></form>';
};

$actions = [];
if ($workspaceType === 'developer') {
    if (in_array($recordResource, ['company', 'project', 'property'], true)) {
        if ($status === 'draft') {
            $actions[] = $workflowButton('pending', 'Submit for review', 'primary');
        } elseif ($status === 'rejected') {
            $actions[] = $workflowButton('draft', 'Return to draft');
        }
    } elseif ($recordResource === 'listing') {
        if ($status === 'draft') {
            $actions[] = $workflowButton('pending', 'Submit for review', 'primary');
        } elseif ($status === 'rejected') {
            $actions[] = $workflowButton('draft', 'Return to draft');
        } elseif ($status === 'published') {
            $actions[] = $workflowButton('paused', 'Pause');
            $actions[] = $workflowButton('closed', 'Close', 'danger');
        } elseif ($status === 'paused') {
            $actions[] = $workflowButton('published', 'Resume', 'primary');
            $actions[] = $workflowButton('closed', 'Close', 'danger');
        }
    } elseif ($recordResource === 'inquiry') {
        if ($status === 'new') {
            $actions[] = $workflowButton('read', 'Mark read', 'primary');
            $actions[] = $workflowButton('spam', 'Mark spam', 'danger');
        } elseif ($status === 'read') {
            $actions[] = $workflowButton('replied', 'Mark replied', 'primary');
            $actions[] = $workflowButton('closed', 'Close');
            $actions[] = $workflowButton('spam', 'Mark spam', 'danger');
        } elseif ($status === 'replied') {
            $actions[] = $workflowButton('closed', 'Close');
        }
    } elseif ($recordResource === 'lead') {
        $next = match ($status) {
            'new' => ['contacted' => 'Contacted'],
            'contacted' => ['qualified' => 'Qualify'],
            'qualified' => ['viewing' => 'Schedule viewing'],
            'viewing' => ['negotiation' => 'Negotiation'],
            'negotiation' => ['won' => 'Mark won', 'lost' => 'Mark lost'],
            'lost' => ['new' => 'Reopen'],
            default => [],
        };
        foreach ($next as $target => $label) {
            $actions[] = $workflowButton(
                $target,
                $label,
                $target === 'won' ? 'primary' : ($target === 'lost' ? 'danger' : 'secondary')
            );
        }
    }
} else {
    if ($status === 'pending' && in_array($recordResource, ['company', 'project', 'property', 'listing'], true)) {
        // Pending moderation uses the structured review panel below so the
        // administrator can capture a decision note before changing state.
    } elseif ($recordResource === 'company' && $status === 'approved') {
        $actions[] = $workflowButton('suspended', 'Suspend', 'danger');
    } elseif ($recordResource === 'company' && $status === 'suspended') {
        $actions[] = $workflowButton('approved', 'Restore', 'primary');
    } elseif ($recordResource === 'project' && $status === 'approved') {
        $actions[] = $workflowButton('published', 'Publish', 'primary');
        $actions[] = $workflowButton('archived', 'Archive', 'danger');
    } elseif ($recordResource === 'project' && $status === 'published') {
        $actions[] = $workflowButton('archived', 'Archive', 'danger');
    } elseif ($recordResource === 'project' && $status === 'archived') {
        $actions[] = $workflowButton('draft', 'Restore to draft');
    } elseif ($recordResource === 'property' && $status === 'approved') {
        $actions[] = $workflowButton('archived', 'Archive', 'danger');
    } elseif ($recordResource === 'property' && $status === 'archived') {
        $actions[] = $workflowButton('draft', 'Restore to draft');
    } elseif ($recordResource === 'listing' && $status === 'published') {
        $actions[] = $workflowButton('paused', 'Pause');
        $actions[] = $workflowButton('closed', 'Close', 'danger');
    } elseif ($recordResource === 'listing' && $status === 'paused') {
        $actions[] = $workflowButton('published', 'Resume', 'primary');
        $actions[] = $workflowButton('closed', 'Close', 'danger');
    } elseif ($recordResource === 'listing' && in_array($status, ['rejected', 'expired'], true)) {
        $actions[] = $workflowButton('draft', 'Restore to draft');
    } elseif ($recordResource === 'media') {
        if ((int) ($record['attachment_count'] ?? 0) === 0) {
            $actions[] = '<form action="' . e(url('/dashboard/media/' . (int) $record['id'] . '/orphan')) . '" method="post" data-workspace-form>'
                . csrf_field()
                . '<button class="btn btn-sm btn-outline-danger" type="submit">Delete orphan</button></form>';
        }
    } elseif ($recordResource === 'page') {
        if ($status === 'draft') {
            $actions[] = '<form action="' . e(url('/dashboard/pages/' . (int) $record['id'] . '/status')) . '" method="post" data-workspace-form>'
                . csrf_field()
                . '<input type="hidden" name="status" value="published">'
                . '<button class="btn btn-sm btn-primary" type="submit">Publish</button></form>';
            $actions[] = '<form action="' . e(url('/dashboard/pages/' . (int) $record['id'] . '/status')) . '" method="post" data-workspace-form>'
                . csrf_field()
                . '<input type="hidden" name="status" value="archived">'
                . '<button class="btn btn-sm btn-outline-danger" type="submit">Archive</button></form>';
        } elseif ($status === 'published') {
            $actions[] = '<a class="btn btn-sm btn-outline-secondary" href="' . e(url(
                '/pages/' . rawurlencode((string) $record['slug'])
            )) . '" target="_blank" rel="noopener noreferrer">View public page</a>';
            $actions[] = '<form action="' . e(url('/dashboard/pages/' . (int) $record['id'] . '/status')) . '" method="post" data-workspace-form>'
                . csrf_field()
                . '<input type="hidden" name="status" value="draft">'
                . '<button class="btn btn-sm btn-outline-secondary" type="submit">Unpublish</button></form>';
            $actions[] = '<form action="' . e(url('/dashboard/pages/' . (int) $record['id'] . '/status')) . '" method="post" data-workspace-form>'
                . csrf_field()
                . '<input type="hidden" name="status" value="archived">'
                . '<button class="btn btn-sm btn-outline-danger" type="submit">Archive</button></form>';
        } elseif ($status === 'archived') {
            $actions[] = '<form action="' . e(url('/dashboard/pages/' . (int) $record['id'] . '/status')) . '" method="post" data-workspace-form>'
                . csrf_field()
                . '<input type="hidden" name="status" value="draft">'
                . '<button class="btn btn-sm btn-primary" type="submit">Restore draft</button></form>';
        }
    } elseif ($recordResource === 'user') {
        $roles = array_filter(array_map(
            'trim',
            explode(',', strtolower((string) ($record['roles'] ?? '')))
        ));
        $isAdministrator = in_array('administrator', $roles, true);
        $isSelf = (int) $record['id'] === $currentUserId;
        $verified = !empty($record['email_verified_at']);
        $accessVerificationState = (string) ($record['access_verification_state'] ?? 'clear');

        if (in_array(
            $accessVerificationState,
            ['developer_access_missing', 'developer_access_orphan'],
            true
        )) {
            $actions[] = '<form action="' . e(url('/dashboard/users/' . (int) $record['id'] . '/developer-access/reconcile')) . '" method="post" data-workspace-form>'
                . csrf_field()
                . '<button class="btn btn-sm btn-primary" type="submit">Reconcile developer access</button></form>';
        }

        if (!$isSelf) {
            if (!$isAdministrator && (in_array('developer', $roles, true) || in_array('company', $roles, true))) {
                $actions[] = '<form action="' . e(url('/dashboard/users/' . (int) $record['id'] . '/company-access/downgrade')) . '" method="post" data-workspace-form data-confirm="Downgrade to Customer and deactivate all company memberships? Transfer ownership first.">'
                    . csrf_field()
                    . '<input type="hidden" name="confirm_downgrade" value="DOWNGRADE">'
                    . '<button class="btn btn-sm btn-outline-danger" type="submit">Downgrade to Customer</button></form>';
            }
            if ($status === 'active') {
                $actions[] = '<form action="' . e(url('/dashboard/users/' . (int) $record['id'] . '/status')) . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<input type="hidden" name="status" value="suspended">'
                    . '<button class="btn btn-sm btn-outline-secondary" type="submit">Suspend</button></form>';
                $actions[] = '<form action="' . e(url('/dashboard/users/' . (int) $record['id'] . '/status')) . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<input type="hidden" name="status" value="deactivated">'
                    . '<button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button></form>';
            } else {
                $actions[] = '<form action="' . e(url('/dashboard/users/' . (int) $record['id'] . '/status')) . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<input type="hidden" name="status" value="active">'
                    . '<button class="btn btn-sm btn-primary" type="submit">Restore</button></form>';
            }

            if (auth_can('roles.manage') && $isAdministrator) {
                $actions[] = '<form action="' . e(url('/dashboard/users/' . (int) $record['id'] . '/administrator')) . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<input type="hidden" name="enabled" value="0">'
                    . '<button class="btn btn-sm btn-outline-secondary" type="submit">Remove admin</button></form>';
            } elseif (auth_can('roles.manage') && $status === 'active' && $verified) {
                $actions[] = '<form action="' . e(url('/dashboard/users/' . (int) $record['id'] . '/administrator')) . '" method="post" data-workspace-form>'
                    . csrf_field()
                    . '<input type="hidden" name="enabled" value="1">'
                    . '<button class="btn btn-sm btn-outline-secondary" type="submit">Make admin</button></form>';
            }

            $actions[] = '<form action="' . e(url('/dashboard/users/' . (int) $record['id'] . '/sessions/revoke')) . '" method="post" data-workspace-form>'
                . csrf_field()
                . '<button class="btn btn-sm btn-outline-danger" type="submit">Revoke sessions</button></form>';
        }
    }
}

$details = match ($recordResource) {
    'company' => [
        'Company type' => $record['company_type'] ?? null,
        'Location' => $record['location_name'] ?? null,
        'Email' => $record['email'] ?? null,
        'Phone' => $record['phone'] ?? null,
        'Website' => $record['website'] ?? null,
        'Registration' => $record['registration_number'] ?? null,
        'Members' => $record['members_count'] ?? 0,
        'Projects' => $record['projects_count'] ?? 0,
        'Properties' => $record['properties_count'] ?? 0,
        'Published listings' => $record['published_listings_count'] ?? 0,
        'Verified' => $record['verified_at'] ?? null,
        'Published' => $record['published_at'] ?? null,
    ],
    'project' => [
        'Company' => $record['company_name'] ?? null,
        'Company status' => $record['company_status'] ?? null,
        'Project type' => $record['project_type'] ?? null,
        'Completion' => $record['completion_status'] ?? null,
        'Location' => $record['location_name'] ?? null,
        'Properties' => $record['properties_count'] ?? 0,
        'Approved properties' => $record['approved_properties_count'] ?? 0,
        'Media assets' => $record['media_count'] ?? 0,
        'Launch date' => $record['launch_date'] ?? null,
        'Expected completion' => $record['expected_completion_date'] ?? null,
        'Published' => $record['published_at'] ?? null,
    ],
    'property' => [
        'Reference' => $record['reference_code'] ?? null,
        'Company' => $record['company_name'] ?? null,
        'Company status' => $record['company_status'] ?? null,
        'Project' => $record['project_name'] ?? null,
        'Project status' => !empty($record['project_id'])
            ? ($record['project_status'] ?? 'unavailable')
            : 'standalone',
        'Type' => $record['property_type'] ?? null,
        'Location' => $record['location_name'] ?? null,
        'Bedrooms' => $record['bedrooms'] ?? null,
        'Bathrooms' => $record['bathrooms'] ?? null,
        'Parking spaces' => $record['parking_spaces'] ?? null,
        'Floor number' => $record['floor_number'] ?? null,
        'Total floors' => $record['total_floors'] ?? null,
        'Area' => trim((string) ($record['area_value'] ?? '') . ' ' . (string) ($record['area_unit'] ?? '')),
        'Land area' => trim((string) ($record['land_area_value'] ?? '') . ' ' . (string) ($record['land_area_unit'] ?? '')),
        'Furnishing' => $record['furnishing_status'] ?? null,
        'Year built' => $record['year_built'] ?? null,
        'Address' => $record['address_line'] ?? null,
        'Listings' => $record['listings_count'] ?? 0,
        'Published listings' => $record['published_listings_count'] ?? 0,
        'Media assets' => $record['media_count'] ?? 0,
    ],
    'listing' => [
        'Property' => $record['property_title'] ?? null,
        'Reference' => $record['reference_code'] ?? null,
        'Property status' => $record['property_status'] ?? null,
        'Company' => $record['company_name'] ?? null,
        'Company status' => $record['company_status'] ?? null,
        'Project' => $record['project_name'] ?? null,
        'Project status' => !empty($record['project_id'])
            ? ($record['project_status'] ?? 'unavailable')
            : 'standalone',
        'Offer' => $record['transaction_type'] ?? null,
        'Price' => ($record['price'] ?? null) !== null
            ? (string) ($record['currency'] ?? 'USD') . ' ' . number_format((float) $record['price'], 2)
            : null,
        'Pricing period' => $record['pricing_period'] ?? null,
        'Media assets' => $record['media_count'] ?? 0,
        'Published' => $record['published_at'] ?? null,
        'Expires' => $record['expires_at'] ?? null,
        'Closed' => $record['closed_at'] ?? null,
    ],
    'inquiry' => [
        'Company' => $record['company_name'] ?? null,
        'Property' => $record['property_title'] ?? null,
        'Reference' => $record['reference_code'] ?? null,
        'Location' => $record['location_name'] ?? null,
        'Offer' => $record['transaction_type'] ?? null,
        'Price' => ($record['price'] ?? null) !== null
            ? (string) ($record['currency'] ?? 'USD') . ' ' . number_format((float) $record['price'], 2)
            : null,
        'Assigned to' => $record['assigned_user_name'] ?? null,
        'Email' => $record['contact_email'] ?? null,
        'Phone' => $record['contact_phone'] ?? null,
        'Preferred contact' => $record['preferred_contact'] ?? null,
        'Source' => $record['source'] ?? null,
        'Received' => $record['created_at'] ?? null,
        'Responded' => $record['responded_at'] ?? null,
        'Lead stage' => $record['converted_lead_stage'] ?? null,
    ],
    'viewing' => [
        'Customer' => $record['customer_name'] ?? null,
        'Customer email' => $record['customer_email'] ?? null,
        'Company' => $record['company_name'] ?? null,
        'Property' => $record['property_title'] ?? null,
        'Reference' => $record['reference_code'] ?? null,
        'Location' => $record['location_name'] ?? null,
        'Offer' => $record['transaction_type'] ?? null,
        'Price' => ($record['price'] ?? null) !== null
            ? (string) ($record['currency'] ?? 'USD') . ' ' . number_format((float) $record['price'], 2)
            : null,
        'Preferred date' => $record['preferred_date'] ?? null,
        'Preferred time' => $record['preferred_time_window'] ?? null,
        'Alternate date' => $record['alternate_date'] ?? null,
        'Alternate time' => $record['alternate_time_window'] ?? null,
        'Scheduled for' => $record['scheduled_for'] ?? null,
        'Requested' => $record['created_at'] ?? null,
    ],
    'lead' => [
        'Company' => $record['company_name'] ?? null,
        'Source inquiry' => $record['source_inquiry_id'] ?? null,
        'Source property' => $record['source_property_title'] ?? null,
        'Property reference' => $record['source_property_reference'] ?? null,
        'Source offer' => $record['source_listing_offer'] ?? null,
        'Source listing price' => ($record['source_listing_price'] ?? null) !== null
            ? (string) ($record['source_listing_currency'] ?? 'USD') . ' ' . number_format((float) $record['source_listing_price'], 2)
            : null,
        'Email' => $record['contact_email'] ?? null,
        'Phone' => $record['contact_phone'] ?? null,
        'Assigned to' => $record['assigned_user_name'] ?? null,
        'Interest' => $record['transaction_type'] ?? null,
        'Property type' => $record['property_type'] ?? null,
        'Preferred location' => $record['preferred_location_name'] ?? null,
        'Budget' => ($record['budget_max'] ?? null) !== null
            ? (string) ($record['currency'] ?? 'USD') . ' ' . number_format((float) $record['budget_max'], 2)
            : null,
        'Last contact' => $record['last_contact_at'] ?? null,
        'Next follow-up' => $record['next_follow_up_at'] ?? null,
    ],
    'media' => [
        'UUID' => $record['uuid'] ?? null,
        'Type' => $record['mime_type'] ?? null,
        'Dimensions' => !empty($record['width_px'])
            ? (string) $record['width_px'] . ' × ' . (string) ($record['height_px'] ?? '?') . ' px'
            : null,
        'Size' => !empty($record['size_bytes'])
            ? number_format(((int) $record['size_bytes']) / 1024, 1) . ' KB'
            : null,
        'Alt text' => $record['alt_text'] ?? null,
        'Attachments' => $record['attachment_count'] ?? 0,
        'Primary uses' => $record['primary_count'] ?? 0,
        'Attachment context' => $record['attachment_context'] ?? null,
        'Uploaded by' => $record['uploaded_by'] ?? null,
        'Checksum SHA-256' => $record['checksum_sha256'] ?? null,
        'Created' => $record['created_at'] ?? null,
        'Updated' => $record['updated_at'] ?? null,
    ],
    'page' => [
        'URL' => '/pages/' . (string) ($record['slug'] ?? ''),
        'Robots' => $record['robots_directive'] ?? null,
        'Published' => $record['published_at'] ?? null,
        'Created by' => $record['created_by_name'] ?? null,
        'Updated by' => $record['updated_by_name'] ?? null,
        'Created' => $record['created_at'] ?? null,
        'Updated' => $record['updated_at'] ?? null,
    ],
    'user' => [
        'Email' => $record['email'] ?? null,
        'Primary access' => $record['account_role'] ?? null,
        'Roles' => $record['roles'] ?? null,
        'Permissions' => $record['permissions'] ?? null,
        'Access verification' => $record['access_verification_state'] ?? 'clear',
        'Active companies' => $record['company_count'] ?? 0,
        'Company owner of' => $record['owned_company_count'] ?? 0,
        'Company access' => $record['company_access'] ?? null,
        'Email verified' => $record['email_verified_at'] ?? null,
        'Last login' => $record['last_login_at'] ?? null,
        'Security events' => $record['security_events_count'] ?? 0,
        'Last security event' => $record['last_security_event_at'] ?? null,
        'Created' => $record['created_at'] ?? null,
        'Updated' => $record['updated_at'] ?? null,
    ],
    'audit' => [
        'Event family' => $record['event_family'] ?? null,
        'Actor' => !empty($record['actor_email'])
            ? trim((string) ($record['actor_name'] ?? '') . ' · ' . (string) $record['actor_email'])
            : 'System',
        'Subject' => !empty($record['subject_email'])
            ? trim((string) ($record['subject_name'] ?? '') . ' · ' . (string) $record['subject_email'])
            : null,
        'Subject status' => $record['subject_status'] ?? null,
        'IP fingerprint' => !empty($record['ip_hash']) ? substr((string) $record['ip_hash'], 0, 16) . '…' : null,
        'User-agent fingerprint' => !empty($record['user_agent_hash']) ? substr((string) $record['user_agent_hash'], 0, 16) . '…' : null,
        'Occurred' => $record['created_at'] ?? null,
    ],
    default => [],
};
?>
<!-- BARAMA_GLOBAL_LOCATION_CASCADE_RECORD_V110 -->
<link rel="stylesheet" href="<?= e(asset('css/global-location-cascade-v110.css') . '?v=110') ?>">
<script src="<?= e(asset('js/global-location-cascade-v110.js') . '?v=110') ?>" defer></script>
<section class="workspace-page workspace-record-page">
    <nav class="workspace-record-breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(url($sectionPath)) ?>"><?= e($humanize($activeSection)) ?></a>
        <span>/</span>
        <span><?= e($title) ?></span>
    </nav>

    <div class="workspace-record-head">
        <div>
            <span class="eyebrow"><?= e($humanize($recordResource)) ?> record</span>
            <h1><?= e($title) ?></h1>
            <div class="workspace-record-status">
                <?php if ($status !== ''): ?>
                    <span class="status-badge status-badge--<?= e(preg_replace('/[^a-z0-9_-]/', '', strtolower($status)) ?: 'neutral') ?>"><?= e($humanize($status)) ?></span>
                <?php elseif ($recordResource === 'audit'): ?>
                    <span class="status-badge status-badge--neutral"><?= e($humanize($record['event_family'] ?? 'security')) ?></span>
                <?php endif; ?>
                <span>#<?= e((string) $record['id']) ?></span>
            </div>
        </div>
        <?php if ($actions !== [] || ($workspaceType === 'admin' && $recordResource === 'company' && $status === 'pending')): ?>
            <div class="workspace-record-actions">
                <?php if ($workspaceType === 'admin' && $recordResource === 'company' && $status === 'pending'): ?>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/approvals?type=company')) ?>"><?= app_icon('shield') ?><span>Approval Queue</span></a>
                <?php endif; ?>
                <?= implode('', $actions) ?>
            </div>
        <?php endif; ?>
    </div>

    <div data-workspace-notice hidden></div>

    <?php if ($workspaceType === 'admin' && $recordResource === 'user'): ?>
        <?php
        $a49UserId = (int) $record['id'];
        $a49Roles = array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($record['roles'] ?? ''))))));
        $a49CanManageRoles = auth_can('roles.manage');
        $a49Verified = !empty($record['email_verified_at']);
        $a49Active = (string) ($record['status'] ?? '') === 'active';
        $a49Self = $a49UserId === $currentUserId;
        $a49Administrator = in_array('administrator', $a49Roles, true);
        $a49Developer = in_array('developer', $a49Roles, true);
        ?>
        <!-- BARAMA_A49A_USER_ACCESS_PANEL_V1 -->
        <section class="workspace-record-panel barama-a49-user-access" id="barama-user-access" aria-label="User access and security management">
            <div class="workspace-record-panel__head"><h2>Access &amp; security management</h2><span>Existing audited role and session operations</span></div>
            <div class="barama-a49-user-access__body">
                <div class="barama-a49-user-access__group">
                    <h3>Role access</h3>
                    <p>Roles determine permissions. Company and developer access depend on verified membership; assigning a role alone cannot create a company workspace.</p>
                    <div class="barama-a49-user-access__roles">
                        <?php foreach ($a49Roles as $a49Role): ?><span class="barama-a49-user-access__role"><?= e(ucwords(str_replace('-', ' ', $a49Role))) ?></span><?php endforeach; ?>
                        <?php if ($a49Roles === []): ?><span>None assigned</span><?php endif; ?>
                    </div>
                    <div class="barama-a49-user-access__actions">
                        <?php if (!$a49Self && $a49CanManageRoles && $a49Active && $a49Verified): ?>
                            <form method="post" action="<?= e(url('/dashboard/users/' . $a49UserId . '/administrator')) ?>" data-workspace-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="enabled" value="<?= $a49Administrator ? '0' : '1' ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $a49Administrator ? 'Remove Administrator role' : 'Grant Administrator role' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($a49Active && $a49Verified): ?>
                            <form method="post" action="<?= e(url('/dashboard/users/' . $a49UserId . '/developer-access/reconcile')) ?>" data-workspace-form>
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Reconcile Developer access</button>
                            </form>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/dashboard/companies')) ?>">Manage company memberships</a>
                    </div>
                    <small>Administrator changes require the Super Administrator permission. Developer access is granted or removed according to active company membership. Super Administrator grants and per-user permission overrides are not available here.</small>
                </div>
                <div class="barama-a49-user-access__group">
                    <h3>Account security</h3>
                    <p><?= $a49Verified ? 'Email identity: verified.' : 'Email identity: unverified.' ?> Password changes and passkey enrollment require secure, user-controlled verification.</p>
                    <div class="barama-a49-user-access__actions">
                        <?php if (!$a49Self): ?>
                            <form method="post" action="<?= e(url('/dashboard/users/' . $a49UserId . '/sessions/revoke')) ?>" data-workspace-form>
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit">Revoke all sessions</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$a49Verified && !$a49Self && $a49CanManageRoles): ?>
                            <form method="post" action="<?= e(url('/dashboard/users/' . $a49UserId . '/verify')) ?>" data-workspace-form data-confirm="Confirm that this user's identity and email ownership were independently verified before proceeding.">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Verify email manually</button>
                            </form>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/dashboard/audit')) ?>">Security audit log</a>
                    </div>
                    <small>Do not collect or display another user's password, reset token, passcode, recovery code, or passkey secret. MFA/passkey enforcement requires a separate enrollment and policy backend.</small>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($workspaceType === 'admin' && $recordResource === 'media'): ?>
        <section class="workspace-record-panel">
            <div class="workspace-record-panel__head">
                <h2>Media metadata</h2>
                <span>Administrators may correct accessibility alt text without changing company-owned gallery ordering or primary-image state.</span>
            </div>

            <?php if (str_starts_with((string) ($record['mime_type'] ?? ''), 'image/')): ?>
                <form action="<?= e(url('/dashboard/media/' . (int) $record['id'] . '/alt-text')) ?>" method="post" data-workspace-form class="workspace-admin-moderation__form">
                    <?= csrf_field() ?>
                    <label class="workspace-admin-moderation__note">
                        <span>Alt text <small>Describe the image for accessibility and discovery</small></span>
                        <textarea class="form-control" name="alt_text" rows="3" maxlength="255" placeholder="Describe the property, project, company, or scene shown."><?= e((string) ($record['alt_text'] ?? '')) ?></textarea>
                    </label>
                    <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Save alt text</button>
                </form>
            <?php else: ?>
                <div class="workspace-record-empty">Alt text management applies to image media only.</div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($workspaceType === 'admin' && $recordResource === 'page'): ?>
        <?= \Barama\Core\View::partial('workspace/page-editor', [
            'workspaceType' => $workspaceType,
            'recordResource' => $recordResource,
            'record' => $record,
        ]) ?>
    <?php endif; ?>

    <?php if ($workspaceType === 'admin' && $status === 'pending' && in_array($recordResource, ['company','project','property','listing'], true)): ?>
        <section class="workspace-admin-moderation" aria-labelledby="admin-moderation-title">
            <div class="workspace-admin-moderation__intro">
                <span class="workspace-admin-moderation__icon"><?= app_icon('shield') ?></span>
                <div>
                    <span class="eyebrow">Moderation decision</span>
                    <h2 id="admin-moderation-title">Review before changing publication state</h2>
                    <p>Confirm the record details and attached media. A rejection reason is required so the submitter knows what must be corrected.</p>
                </div>
            </div>
            <?php if (!$moderationReady): ?>
                <div class="workspace-lock-note">
                    <?= app_icon('alert') ?>
                    <div>
                        <strong>Approval is blocked by readiness requirements</strong>
                        <ul class="workspace-moderation-blockers">
                            <?php foreach ($moderationBlockers as $moderationBlocker): ?>
                                <li><?= e((string) $moderationBlocker) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <span>You can still reject the record with correction instructions.</span>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('/dashboard/workflow/' . rawurlencode($recordResource) . '/' . (int) $record['id'])) ?>" method="post" data-workspace-form class="workspace-admin-moderation__form">
                <?= csrf_field() ?>
                <label>
                    <span>Decision</span>
                    <select class="form-select" name="status" required>
                        <option value="">Choose a decision</option>
                        <option
                            value="<?= $recordResource === 'listing' ? 'published' : 'approved' ?>"
                            <?= !$moderationReady ? 'disabled' : '' ?>
                        ><?= $recordResource === 'listing' ? 'Approve & publish' : 'Approve' ?></option>
                        <option value="rejected">Reject and return for correction</option>
                    </select>
                </label>
                <label class="workspace-admin-moderation__note">
                    <span>Decision note <small>Required for rejection</small></span>
                    <textarea class="form-control" name="note" rows="4" maxlength="500" placeholder="Add review context, approval notes, or the exact corrections required for rejection."></textarea>
                </label>
                <button class="btn btn-primary" type="submit"><?= app_icon('check') ?> Apply decision</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($workspaceType === 'admin' && $recordResource === 'inquiry'): ?>
        <section class="workspace-record-panel workspace-customer-operation-panel">
            <div class="workspace-record-panel__head">
                <h2>Inquiry oversight</h2>
                <span>Platform-level visibility; the company remains responsible for customer follow-up and qualification.</span>
            </div>
            <?php if (!empty($record['converted_lead_id'])): ?>
                <div class="workspace-operation-ready">
                    <span class="workspace-operation-ready__icon"><?= app_icon('pipeline') ?></span>
                    <div>
                        <strong>Converted to a company property lead</strong>
                        <span>Lead stage: <?= e($humanize($record['converted_lead_stage'] ?? 'new')) ?>.</span>
                    </div>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('/dashboard/manage/lead/' . (int) $record['converted_lead_id'])) ?>">Open linked lead</a>
                </div>
            <?php else: ?>
                <div class="workspace-record-empty">This inquiry has not been converted to a property lead. Lead qualification remains a company workspace action.</div>
            <?php endif; ?>
        </section>
    <?php elseif ($workspaceType === 'admin' && $recordResource === 'lead'): ?>
        <section class="workspace-record-panel workspace-customer-operation-panel">
            <div class="workspace-record-panel__head">
                <h2>Company-owned property lead</h2>
                <span>Administrator access is oversight-only. Pipeline progression and follow-up remain with the company team.</span>
            </div>
            <div class="workspace-operation-ready">
                <span class="workspace-operation-ready__icon"><?= app_icon('shield') ?></span>
                <div>
                    <strong><?= e((string) ($record['company_name'] ?? 'Company')) ?> pipeline</strong>
                    <span>Current stage: <?= e($humanize($record['stage'] ?? 'new')) ?><?= !empty($record['assigned_user_name']) ? ' · Assigned to ' . e((string) $record['assigned_user_name']) : '' ?>.</span>
                </div>
                <?php if (!empty($record['source_inquiry_id'])): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/dashboard/manage/inquiry/' . (int) $record['source_inquiry_id'])) ?>">Open source inquiry</a>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($workspaceType === 'admin' && $recordResource === 'viewing'): ?>
        <section class="workspace-record-panel workspace-customer-operation-panel">
            <div class="workspace-record-panel__head">
                <h2>Company-managed viewing appointment</h2>
                <span>Administrator access is oversight-only. Confirmation, rescheduling, completion, and decline decisions remain with the company team.</span>
            </div>
            <div class="workspace-operation-ready">
                <span class="workspace-operation-ready__icon"><?= app_icon('calendar') ?></span>
                <div>
                    <strong><?= e((string) ($record['company_name'] ?? 'Company')) ?> appointment</strong>
                    <span>
                        Current status: <?= e($humanize($record['status'] ?? 'pending')) ?>
                        <?= !empty($record['scheduled_for']) ? ' · Scheduled ' . e((string) $record['scheduled_for']) : '' ?>.
                    </span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($workspaceType === 'admin' && $recordResource === 'user'): ?>
        <section class="workspace-record-panel workspace-customer-operation-panel">
            <div class="workspace-record-panel__head">
                <h2>Account & real-estate access safeguards</h2>
                <span>Account administration is security-scoped; company ownership and workspace access remain part of the real-estate domain.</span>
            </div>
            <div class="workspace-operation-ready">
                <span class="workspace-operation-ready__icon"><?= app_icon('building') ?></span>
                <div>
                    <strong><?= e(number_format((int) ($record['company_count'] ?? 0))) ?> active company connection<?= (int) ($record['company_count'] ?? 0) === 1 ? '' : 's' ?></strong>
                    <span><?= (int) ($record['owned_company_count'] ?? 0) > 0
                        ? e(number_format((int) $record['owned_company_count']) . ' company ownership assignment(s). Sole owners must transfer ownership before account disablement.')
                        : 'No active company ownership blocks account disablement.' ?></span>
                    <?php if (($record['access_verification_state'] ?? 'clear') !== 'clear'): ?>
                        <span>Verification attention: <?= e($humanize($record['access_verification_state'])) ?>.</span>
                    <?php else: ?>
                        <span>Email and membership-derived workspace access are consistent.</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php elseif ($workspaceType === 'admin' && $recordResource === 'audit'): ?>
        <section class="workspace-record-panel workspace-customer-operation-panel">
            <div class="workspace-record-panel__head">
                <h2>Immutable security event</h2>
                <span>This event is read-only. Raw IP addresses and user-agent strings are not stored here; only SHA-256 fingerprints are retained.</span>
            </div>
            <?php if ($auditMetadata !== []): ?>
                <dl class="workspace-record-details">
                    <?php foreach ($auditMetadata as $key => $value): ?>
                        <?php if (is_scalar($value) || $value === null): ?>
                            <div>
                                <dt><?= e($humanize($key)) ?></dt>
                                <dd><?= $value === null || $value === '' ? '—' : e((string) $value) ?></dd>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            <?php else: ?>
                <div class="workspace-record-empty">No safe metadata was recorded for this event.</div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($workspaceType === 'developer' && in_array($recordResource, ['company','project','property','listing'], true) && !$editable): ?>
        <div class="workspace-lock-note">
            <?= app_icon('shield') ?>
            <div>
                <strong>Reviewed content is locked</strong>
                <span>This record can be viewed and moved through its allowed workflow, but content and media cannot be changed while it is in the <?= e($humanize($status)) ?> state.</span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($workspaceType === 'developer' && in_array($recordResource, ['company','project','property','listing'], true)): ?>
        <?php
        $portfolioState = match (true) {
            $status === 'draft' => ['edit', 'Draft — ready for editing', 'Complete the record and submit it for review when it is ready.'],
            $status === 'pending' => ['clock', 'Pending marketplace review', 'Content is locked while an administrator reviews this submission.'],
            $status === 'rejected' => ['alert', 'Changes requested', 'Review the moderation history, return the record to draft, make corrections, then resubmit.'],
            $recordResource === 'company' && $status === 'approved' => ['shield', 'Company approved', 'This company can own approved marketplace inventory and workspace members.'],
            $recordResource === 'project' && $status === 'approved' => ['shield', 'Project approved', 'The project passed moderation and can now be published.'],
            $recordResource === 'project' && $status === 'published' => ['home', 'Project published', 'This development project is available to the marketplace.'],
            $recordResource === 'property' && $status === 'approved' => ['shield', 'Property approved', 'The canonical property can now be used for sale or rental listings.'],
            $recordResource === 'listing' && $status === 'published' => ['tag', 'Listing live', 'The offer is visible to marketplace customers until paused, closed, or expired.'],
            $recordResource === 'listing' && $status === 'paused' => ['clock', 'Listing paused', 'The offer is temporarily hidden and can be resumed or closed.'],
            in_array($status, ['archived','closed','expired','suspended'], true) => ['shield', $humanize($status), 'This record is outside the active marketplace lifecycle.'],
            default => ['shield', $humanize($status), 'This record remains protected by company-scoped workspace access.'],
        };
        ?>
        <section class="workspace-portfolio-state">
            <span class="workspace-portfolio-state__icon"><?= app_icon((string) $portfolioState[0]) ?></span>
            <div>
                <strong><?= e((string) $portfolioState[1]) ?></strong>
                <span><?= e((string) $portfolioState[2]) ?></span>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($recordResource === 'property' && is_array($propertyReadiness)): ?>
        <section class="workspace-record-panel property-readiness" aria-labelledby="property-readiness-title">
            <div class="workspace-record-panel__head">
                <div>
                    <h2 id="property-readiness-title">Property readiness &amp; quality</h2>
                    <span>Computed from canonical property data, approval dependencies, and attached media. Listing counts are context only.</span>
                </div>
                <span class="property-readiness__state property-readiness__state--<?= e((string) $propertyReadiness['level']) ?>">
                    <?= e((string) $propertyReadiness['label']) ?>
                </span>
            </div>

            <div class="property-readiness__body">
                <div class="property-readiness__summary">
                    <div class="property-readiness__score-row">
                        <div class="property-readiness__score-ring" data-barama-readiness-score="<?= (int) $propertyReadiness['score'] ?>" aria-label="Readiness score <?= (int) $propertyReadiness['score'] ?> out of 100">
                            <div class="property-readiness__score-value">
                                <strong><?= (int) $propertyReadiness['score'] ?></strong>
                                <small>/ 100</small>
                            </div>
                        </div>
                        <div class="property-readiness__score-copy">
                            <strong><?= !empty($propertyReadiness['approval_ready']) ? 'Approval-ready record' : 'Record needs attention' ?></strong>
                            <span>
                                <?= !empty($propertyReadiness['approval_ready'])
                                    ? 'All scored readiness checks are complete.'
                                    : count((array) $propertyReadiness['missing']) . ' scored check(s) still need attention.' ?>
                            </span>
                        </div>
                    </div>

                    <?php if (
                        $workspaceType === 'developer'
                        && in_array($status, ['draft', 'rejected'], true)
                        && is_array($propertyReadiness['first_missing'] ?? null)
                    ): ?>
                        <?php $firstReadinessIssue = (array) $propertyReadiness['first_missing']; ?>
                        <div class="property-readiness__next-action" id="property-readiness-next-action">
                            <span aria-hidden="true"><?= app_icon('alert') ?></span>
                            <div>
                                <strong>Fix first: <?= e((string) ($firstReadinessIssue['label'] ?? 'Property requirement')) ?></strong>
                                <small><?= e((string) ($firstReadinessIssue['message'] ?? 'Complete the next readiness requirement.')) ?></small>
                            </div>
                            <a class="btn btn-sm btn-outline-secondary" href="#workspace-record-edit">Open editor</a>
                        </div>
                    <?php endif; ?>

                    <div class="property-readiness__context">
                        <div>
                            <span>Listing context</span>
                            <strong><?= (int) ($propertyReadiness['listing']['total'] ?? 0) ?> total</strong>
                            <small><?= (int) ($propertyReadiness['listing']['published'] ?? 0) ?> published · not scored</small>
                        </div>
                        <div>
                            <span>Optional enrichment</span>
                            <strong><?= (int) ($propertyReadiness['enrichment']['filled'] ?? 0) ?>/<?= (int) ($propertyReadiness['enrichment']['total'] ?? 0) ?></strong>
                            <small>Bedrooms, floors, furnishing, land area, and related optional details.</small>
                        </div>
                    </div>
                </div>

                <ul class="property-readiness__checklist" aria-label="Property readiness checklist">
                    <?php foreach ((array) $propertyReadiness['checks'] as $readinessCheck): ?>
                        <?php $readinessPassed = !empty($readinessCheck['passed']); ?>
                        <li class="property-readiness__check <?= $readinessPassed ? 'is-pass' : 'is-missing' ?>">
                            <span class="property-readiness__check-icon" aria-hidden="true"><?= app_icon($readinessPassed ? 'check' : 'alert') ?></span>
                            <span class="property-readiness__check-copy">
                                <strong><?= e((string) ($readinessCheck['label'] ?? 'Readiness check')) ?></strong>
                                <small><?= e((string) ($readinessCheck['message'] ?? '')) ?></small>
                            </span>
                            <span class="property-readiness__weight"><?= (int) ($readinessCheck['weight'] ?? 0) ?> pts</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <div class="workspace-record-layout">
        <div class="workspace-record-main">
            <section class="workspace-record-panel">
                <div class="workspace-record-panel__head"><h2>Record details</h2></div>
                <dl class="workspace-record-details">
                    <?php foreach ($details as $label => $value): ?>
                        <?php
                        $humanizedLabels = [
                            'Company type', 'Project type', 'Completion', 'Type',
                            'Offer', 'Pricing period', 'Preferred contact', 'Source',
                            'Interest', 'Property type', 'Preferred time', 'Alternate time',
                            'Primary access', 'Event family', 'Subject status',
                        ];
                        $displayValue = in_array($label, $humanizedLabels, true)
                            ? $humanize($value)
                            : (string) $value;
                        ?>
                        <div><dt><?= e($label) ?></dt><dd><?= $value === null || $value === '' ? '—' : e($displayValue) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
                <?php
                $longText = $record['description'] ?? $record['property_description'] ?? $record['message'] ?? $record['notes'] ?? null;
                $copyHeading = $recordResource === 'inquiry'
                    ? 'Message'
                    : ($recordResource === 'lead'
                        ? 'Notes'
                        : ($recordResource === 'viewing' ? 'Customer message' : 'Description'));
                ?>
                <?php if (!empty($longText)): ?>
                    <div class="workspace-record-copy">
                        <h3><?= e($copyHeading) ?></h3>
                        <?php if (in_array($recordResource, ['company', 'project', 'property', 'listing'], true)): ?>
                            <div class="rich-text-content rich-text-content--workspace"><?= \Barama\Support\RichText::render((string) $longText) ?></div>
                        <?php else: ?>
                            <p><?= nl2br(e((string) $longText)) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($workspaceType === 'developer' && $recordResource === 'inquiry'): ?>
                <section class="workspace-record-panel workspace-customer-operation-panel">
                    <div class="workspace-record-panel__head">
                        <h2>Property lead qualification</h2>
                        <span>Convert only genuine listing interest into the company property-lead pipeline.</span>
                    </div>

                    <?php if (!empty($record['converted_lead_id'])): ?>
                        <div class="workspace-operation-ready">
                            <span class="workspace-operation-ready__icon"><?= app_icon('pipeline') ?></span>
                            <div>
                                <strong>Inquiry converted to a property lead</strong>
                                <span>Current lead stage: <?= e($humanize($record['converted_lead_stage'] ?? 'new')) ?>.</span>
                            </div>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('/developer/manage/lead/' . (int) $record['converted_lead_id'])) ?>">Open lead</a>
                        </div>
                    <?php elseif (in_array($status, ['new', 'read', 'replied'], true)): ?>
                        <form action="<?= e(url('/developer/inquiries/' . (int) $record['id'] . '/lead')) ?>" method="post" data-workspace-form class="workspace-form">
                            <?= csrf_field() ?>
                            <div class="workspace-form__grid">
                                <label><span>Interest</span><select class="form-select" name="transaction_type"><option value="">Not specified</option><option value="sale" <?= ($record['transaction_type'] ?? '') === 'sale' ? 'selected' : '' ?>>For sale</option><option value="rent" <?= ($record['transaction_type'] ?? '') === 'rent' ? 'selected' : '' ?>>For rent</option></select></label>
                                <label><span>Property type</span><select class="form-select" name="property_type"><option value="">Not specified</option><?php foreach (['apartment','condominium','house','villa','townhouse','land','office','retail','warehouse','industrial','hotel','commercial','other'] as $type): ?><option value="<?= e($type) ?>" <?= ($record['property_type'] ?? '') === $type ? 'selected' : '' ?>><?= e($humanize($type)) ?></option><?php endforeach; ?></select></label>
                                <?= \Barama\Core\View::partial('workspace/location-cascade', [
    'fieldName' => 'preferred_location_id',
    'currentLocationId' => (int) ($record['location_id'] ?? 0),
    'label' => 'Preferred location',
    'spanTwo' => true,
]) ?>
                                <label><span>Currency</span><input class="form-control" name="currency" value="<?= e((string) ($record['currency'] ?? 'USD')) ?>" maxlength="3"></label>
                                <label><span>Minimum budget</span><input class="form-control" type="number" min="0" step="0.01" name="budget_min"></label>
                                <label><span>Maximum budget</span><input class="form-control" type="number" min="0" step="0.01" name="budget_max"></label>
                                <label class="workspace-form__span-2"><span>Qualification notes</span><textarea class="form-control" name="notes" rows="4" maxlength="10000" placeholder="Property requirements or qualification notes relevant to this inquiry"></textarea></label>
                            </div>
                            <button class="btn btn-primary" type="submit"><?= app_icon('pipeline') ?> Convert to property lead</button>
                        </form>
                    <?php else: ?>
                        <div class="workspace-record-empty">Closed or spam inquiries cannot be converted into property leads.</div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($workspaceType === 'developer' && $recordResource === 'viewing'): ?>
                <section class="workspace-record-panel">
                    <div class="workspace-record-panel__head">
                        <h2>Viewing schedule</h2>
                        <span>Company-managed appointment status</span>
                    </div>

                    <?php if ($viewingTransitions !== []): ?>
                        <form action="<?= e(url('/developer/viewings/' . (int) $record['id'])) ?>" method="post" data-workspace-form class="workspace-form">
                            <?= csrf_field() ?>
                            <div class="workspace-form__grid">
                                <label>
                                    <span>Next status</span>
                                    <select class="form-select" name="status" required>
                                        <?php foreach ($viewingTransitions as $target => $label): ?>
                                            <option value="<?= e($target) ?>"><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span>Scheduled date & time</span>
                                    <input class="form-control" type="datetime-local" name="scheduled_for" value="<?= e($scheduledLocal) ?>">
                                </label>
                                <label class="workspace-form__span-2">
                                    <span>Company note</span>
                                    <textarea class="form-control" name="company_note" rows="4" maxlength="1000" placeholder="Appointment instructions or reason for rescheduling/declining"><?= e((string) ($record['company_note'] ?? '')) ?></textarea>
                                </label>
                            </div>
                            <button class="btn btn-primary" type="submit">Update viewing</button>
                        </form>
                    <?php else: ?>
                        <div class="workspace-record-empty">
                            This viewing is in a terminal state and no further company scheduling action is available.
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($editable): ?>
                <section class="workspace-record-panel" id="workspace-record-edit">
                    <div class="workspace-record-panel__head"><h2>Edit <?= e($humanize($recordResource)) ?></h2><span><?= e($workspaceType === 'admin' ? 'Administrator edit · current workflow status is preserved.' : 'Changes remain in the review workflow.') ?></span></div>

                    <?php if ($recordResource === 'company'): ?>
                        <form action="<?= e(url(
                            $workspaceType === 'admin'
                                ? '/dashboard/companies/' . (int) $record['id']
                                : '/developer/companies/' . (int) $record['id']
                        )) ?>" method="post" data-workspace-form class="workspace-form">
                            <?= csrf_field() ?>
                            <div class="workspace-form__grid">
                                <label><span>Company name</span><input class="form-control" name="name" value="<?= e((string) $record['name']) ?>" required maxlength="180"></label>
                                <label><span>Company type</span><select class="form-select" name="company_type"><?php foreach (['developer','agency','property_company','owner'] as $type): ?><option value="<?= e($type) ?>" <?= $record['company_type'] === $type ? 'selected' : '' ?>><?= e($humanize($type)) ?></option><?php endforeach; ?></select></label>
                                <label><span>Legal name</span><input class="form-control" name="legal_name" value="<?= e((string) ($record['legal_name'] ?? '')) ?>" maxlength="191"></label>
                                <label><span>Email</span><input class="form-control" name="email" type="email" value="<?= e((string) ($record['email'] ?? '')) ?>" maxlength="191"></label>
                                <label><span>Phone</span><input class="form-control" name="phone" value="<?= e((string) ($record['phone'] ?? '')) ?>" maxlength="60"></label>
                                <label><span>Website</span><input class="form-control" name="website" value="<?= e((string) ($record['website'] ?? '')) ?>" maxlength="255"></label>
                                <label><span>Registration number</span><input class="form-control" name="registration_number" value="<?= e((string) ($record['registration_number'] ?? '')) ?>" maxlength="120"></label>
                                <label><span>Tax number</span><input class="form-control" name="tax_number" value="<?= e((string) ($record['tax_number'] ?? '')) ?>" maxlength="120"></label>
                                <?= \Barama\Core\View::partial('workspace/location-cascade', [
    'fieldName' => 'location_id',
    'currentLocationId' => (int) ($record['location_id'] ?? 0),
    'label' => 'Location',
    'spanTwo' => true,
]) ?>
                                <label class="workspace-form__span-2"><span>Address</span><input class="form-control" name="address_line" value="<?= e((string) ($record['address_line'] ?? '')) ?>" maxlength="255"></label>
                                <?= \Barama\Core\View::partial('workspace/description-editor', [
                                    'value' => (string) ($record['description'] ?? ''),
                                    'editorId' => 'workspace-description-' . $recordResource . '-' . (int) $record['id'],
                                ]) ?>
                            </div>
                            <button class="btn btn-primary" type="submit">Save changes</button>
                        </form>
                    <?php elseif ($recordResource === 'project'): ?>
                        <form action="<?= e(url(
                            $workspaceType === 'admin'
                                ? '/dashboard/projects/' . (int) $record['id']
                                : '/developer/projects/' . (int) $record['id']
                        )) ?>" method="post" data-workspace-form class="workspace-form">
                            <?= csrf_field() ?>
                            <div class="workspace-form__grid">
                                <label><span>Project name</span><input class="form-control" name="name" value="<?= e((string) $record['name']) ?>" required maxlength="191"></label>
                                <label><span>Project type</span><select class="form-select" name="project_type"><?php foreach (['residential','commercial','mixed_use','land_development','industrial','hospitality','other'] as $type): ?><option value="<?= e($type) ?>" <?= $record['project_type'] === $type ? 'selected' : '' ?>><?= e($humanize($type)) ?></option><?php endforeach; ?></select></label>
                                <label><span>Completion</span><select class="form-select" name="completion_status"><?php foreach (['planned','pre_launch','under_construction','completed','on_hold'] as $stage): ?><option value="<?= e($stage) ?>" <?= $record['completion_status'] === $stage ? 'selected' : '' ?>><?= e($humanize($stage)) ?></option><?php endforeach; ?></select></label>
                                <?= \Barama\Core\View::partial('workspace/location-cascade', [
    'fieldName' => 'location_id',
    'currentLocationId' => (int) ($record['location_id'] ?? 0),
    'label' => 'Location',
    'spanTwo' => true,
]) ?>
                                <label><span>Launch date</span><input class="form-control" type="date" name="launch_date" value="<?= e((string) ($record['launch_date'] ?? '')) ?>"></label>
                                <label><span>Expected completion</span><input class="form-control" type="date" name="expected_completion_date" value="<?= e((string) ($record['expected_completion_date'] ?? '')) ?>"></label>
                                <label class="workspace-form__span-2"><span>Address</span><input class="form-control" name="address_line" value="<?= e((string) ($record['address_line'] ?? '')) ?>" maxlength="255"></label>
                                <?= \Barama\Core\View::partial('workspace/description-editor', [
                                    'value' => (string) ($record['description'] ?? ''),
                                    'editorId' => 'workspace-description-' . $recordResource . '-' . (int) $record['id'],
                                ]) ?>
                            </div>
                            <button class="btn btn-primary" type="submit">Save changes</button>
                        </form>
                    <?php elseif ($recordResource === 'property'): ?>
                        <form action="<?= e(url(
                            $workspaceType === 'admin'
                                ? '/dashboard/properties/' . (int) $record['id']
                                : '/developer/properties/' . (int) $record['id']
                        )) ?>" method="post" data-workspace-form class="workspace-form">
                            <?= csrf_field() ?>
                            <div class="workspace-form__grid">
                                <label><span>Title</span><input class="form-control" name="title" value="<?= e((string) $record['title']) ?>" required maxlength="191"></label>
                                <label><span>Type</span><select class="form-select" name="property_type"><?php foreach (['apartment','condominium','house','villa','townhouse','land','office','retail','warehouse','industrial','hotel','commercial','other'] as $type): ?><option value="<?= e($type) ?>" <?= $record['property_type'] === $type ? 'selected' : '' ?>><?= e($humanize($type)) ?></option><?php endforeach; ?></select></label>
                                <label><span>Project</span><select class="form-select" name="project_id"><option value="">Standalone</option><?php foreach ($projects as $project): ?><option value="<?= e((string) $project['id']) ?>" <?= (int) ($record['project_id'] ?? 0) === (int) $project['id'] ? 'selected' : '' ?>><?= e((string) $project['name']) ?></option><?php endforeach; ?></select></label>
                                <?= \Barama\Core\View::partial('workspace/location-cascade', [
    'fieldName' => 'location_id',
    'currentLocationId' => (int) ($record['location_id'] ?? 0),
    'label' => 'Location',
    'spanTwo' => true,
]) ?>
                                <label><span>Bedrooms</span><input class="form-control" type="number" min="0" step="0.5" name="bedrooms" value="<?= e((string) ($record['bedrooms'] ?? '')) ?>"></label>
                                <label><span>Bathrooms</span><input class="form-control" type="number" min="0" step="0.5" name="bathrooms" value="<?= e((string) ($record['bathrooms'] ?? '')) ?>"></label>
                                <label><span>Parking spaces</span><input class="form-control" type="number" min="0" name="parking_spaces" value="<?= e((string) ($record['parking_spaces'] ?? '')) ?>"></label>
                                <label><span>Floor number</span><input class="form-control" type="number" name="floor_number" value="<?= e((string) ($record['floor_number'] ?? '')) ?>"></label>
                                <label><span>Total floors</span><input class="form-control" type="number" min="1" name="total_floors" value="<?= e((string) ($record['total_floors'] ?? '')) ?>"></label>
                                <label><span>Year built</span><input class="form-control" type="number" min="1000" max="9999" name="year_built" value="<?= e((string) ($record['year_built'] ?? '')) ?>"></label>
                                <label><span>Area</span><input class="form-control" type="number" min="0" step="0.01" name="area_value" value="<?= e((string) ($record['area_value'] ?? '')) ?>"></label>
                                <label><span>Area unit</span><input class="form-control" name="area_unit" value="<?= e((string) ($record['area_unit'] ?? 'sqm')) ?>" maxlength="20"></label>
                                <label><span>Land area</span><input class="form-control" type="number" min="0" step="0.01" name="land_area_value" value="<?= e((string) ($record['land_area_value'] ?? '')) ?>"></label>
                                <label><span>Land area unit</span><input class="form-control" name="land_area_unit" value="<?= e((string) ($record['land_area_unit'] ?? '')) ?>" maxlength="20"></label>
                                <label><span>Furnishing</span><input class="form-control" name="furnishing_status" value="<?= e((string) ($record['furnishing_status'] ?? '')) ?>" maxlength="32"></label>
                                <label class="workspace-form__span-2"><span>Address</span><input class="form-control" name="address_line" value="<?= e((string) ($record['address_line'] ?? '')) ?>" maxlength="255"></label>
                                <?= \Barama\Core\View::partial('workspace/description-editor', [
                                    'value' => (string) ($record['description'] ?? ''),
                                    'editorId' => 'workspace-description-' . $recordResource . '-' . (int) $record['id'],
                                ]) ?>
                            </div>
                            <button class="btn btn-primary" type="submit">Save changes</button>
                        </form>
                    <?php elseif ($recordResource === 'listing'): ?>
                        <form action="<?= e(url('/developer/listings/' . (int) $record['id'])) ?>" method="post" data-workspace-form class="workspace-form">
                            <?= csrf_field() ?>
                            <div class="workspace-form__grid">
                                <label><span>Offer</span><select class="form-select" name="transaction_type"><option value="sale" <?= $record['transaction_type'] === 'sale' ? 'selected' : '' ?>>For sale</option><option value="rent" <?= $record['transaction_type'] === 'rent' ? 'selected' : '' ?>>For rent</option></select></label>
                                <label><span>Price</span><input class="form-control" type="number" min="0" step="0.01" name="price" value="<?= e((string) ($record['price'] ?? '')) ?>" required></label>
                                <label><span>Currency</span><input class="form-control" name="currency" value="<?= e((string) ($record['currency'] ?? 'USD')) ?>" maxlength="3" required></label>
                                <label><span>Pricing period</span><select class="form-select" name="pricing_period"><option value="">Not applicable</option><?php foreach (['day','week','month','year'] as $period): ?><option value="<?= e($period) ?>" <?= ($record['pricing_period'] ?? '') === $period ? 'selected' : '' ?>><?= e($humanize($period)) ?></option><?php endforeach; ?></select></label>
                                <label><span>Deposit amount</span><input class="form-control" type="number" min="0" step="0.01" name="deposit_amount" value="<?= e((string) ($record['deposit_amount'] ?? '')) ?>"></label>
                                <label><span>Available from</span><input class="form-control" type="date" name="available_from" value="<?= e((string) ($record['available_from'] ?? '')) ?>"></label>
                                <label class="workspace-form__span-2"><span>Expires at</span><input class="form-control" name="expires_at" value="<?= e((string) ($record['expires_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS" maxlength="19"></label>
                                <label class="workspace-check workspace-form__span-2"><input type="checkbox" name="negotiable" value="1" <?= !empty($record['negotiable']) ? 'checked' : '' ?>><span>Price is negotiable</span></label>
                            </div>
                            <button class="btn btn-primary" type="submit">Save changes</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (in_array($recordResource, ['company','project','property','listing'], true)): ?>
                <section class="workspace-record-panel">
                    <div class="workspace-record-panel__head"><h2>Media gallery</h2><span><?= e((string) count($media)) ?> attachment<?= count($media) === 1 ? '' : 's' ?></span></div>

                    <?php if ($media === []): ?>
                        <div class="workspace-record-empty">No media is attached to this record.</div>
                    <?php else: ?>
                        <div class="workspace-media-grid">
                            <?php foreach ($media as $item): ?>
                                <article class="workspace-media-item">
                                    <div class="workspace-media-item__preview">
                                        <?php if (str_starts_with((string) $item['mime_type'], 'image/') && $mediaPublic): ?>
                                            <img src="<?= e(url('/media/' . rawurlencode((string) $item['uuid']))) ?>" alt="<?= e((string) ($item['alt_text'] ?: $item['original_name'])) ?>">
                                        <?php else: ?>
                                            <?= app_icon('image') ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="workspace-media-item__body">
                                        <strong><?= e((string) $item['original_name']) ?></strong>
                                        <span><?= e((string) $item['mime_type']) ?> · <?= e($humanize($item['collection'] ?? 'gallery')) ?><?= !empty($item['is_primary']) ? ' · Primary' : '' ?></span>
                                        <?php if (!empty($item['alt_text'])): ?><small class="workspace-media-item__alt"><?= e((string) $item['alt_text']) ?></small><?php endif; ?>
                                        <?php if ($editable): ?>
                                            <form action="<?= e(url('/developer/media/' . (int) $item['attachment_id'] . '/alt-text')) ?>" method="post" data-workspace-form class="workspace-media-alt-form">
                                                <?= csrf_field() ?>
                                                <label>
                                                    <span>Alt text</span>
                                                    <input class="form-control" name="alt_text" value="<?= e((string) ($item['alt_text'] ?? '')) ?>" maxlength="255" placeholder="Describe this real-estate image">
                                                </label>
                                                <button type="submit">Update alt text</button>
                                            </form>
                                            <div class="workspace-media-item__actions">
                                                <?php if (empty($item['is_primary']) && str_starts_with((string) $item['mime_type'], 'image/')): ?>
                                                    <form action="<?= e(url('/developer/media/' . (int) $item['attachment_id'] . '/primary')) ?>" method="post" data-workspace-form><?= csrf_field() ?><button type="submit">Make primary</button></form>
                                                <?php endif; ?>
                                                <form action="<?= e(url('/developer/media/' . (int) $item['attachment_id'] . '/remove')) ?>" method="post" data-workspace-form><button class="is-danger table-action--danger" type="submit">Remove</button><?= csrf_field() ?></form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($editable): ?>
                        <form action="<?= e(url('/developer/media')) ?>" method="post" enctype="multipart/form-data" data-workspace-form class="workspace-record-upload">
                            <?= csrf_field() ?>
                            <input type="hidden" name="entity_type" value="<?= e($recordResource) ?>">
                            <input type="hidden" name="entity_id" value="<?= e((string) $record['id']) ?>">
                            <input type="hidden" name="collection" value="gallery">
                            <label><span>Upload image or PDF</span><input class="form-control" type="file" name="file" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
                            <label><span>Alt text</span><input class="form-control" name="alt_text" maxlength="255"></label>
                            <label class="workspace-check"><input type="checkbox" name="is_primary" value="1"><span>Use as primary image</span></label>
                            <button class="btn btn-primary" type="submit">Upload media</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>

        <aside class="workspace-record-side">
            <?php if ($recordResource === 'user'): ?>
                <section class="workspace-record-panel">
                    <div class="workspace-record-panel__head"><h2>Security posture</h2></div>
                    <dl class="workspace-record-details">
                        <div><dt>Account status</dt><dd><?= e($humanize($record['status'] ?? 'active')) ?></dd></div>
                        <div><dt>Email</dt><dd><?= !empty($record['email_verified_at']) ? 'Verified' : 'Unverified' ?></dd></div>
                        <div><dt>Recorded security events</dt><dd><?= e(number_format((int) ($record['security_events_count'] ?? 0))) ?></dd></div>
                        <div><dt>Session generation</dt><dd><?= e((string) ($record['auth_version'] ?? 1)) ?></dd></div>
                    </dl>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/dashboard/audit?q=' . rawurlencode((string) ($record['email'] ?? '')))) ?>">View related audit events</a>
                </section>
            <?php elseif ($recordResource === 'audit'): ?>
                <section class="workspace-record-panel">
                    <div class="workspace-record-panel__head"><h2>Audit integrity</h2></div>
                    <div class="workspace-record-empty">No edit, delete, or workflow action is available for security-audit records.</div>
                    <?php if (!empty($record['subject_user_id'])): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/dashboard/manage/user/' . (int) $record['subject_user_id'])) ?>">Open subject account</a>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="workspace-record-panel">
                    <div class="workspace-record-panel__head"><h2>Workflow history</h2></div>
                    <?php if ($history === []): ?>
                        <div class="workspace-record-empty">No status changes have been recorded yet.</div>
                    <?php else: ?>
                        <ol class="workspace-history">
                            <?php foreach ($history as $entry): ?>
                                <li>
                                    <span class="workspace-history__dot"></span>
                                    <div>
                                        <strong><?= e($humanize($entry['from_status'] ?? '')) ?> → <?= e($humanize($entry['to_status'] ?? '')) ?></strong>
                                        <small><?= e((string) ($entry['actor_name'] ?? 'System')) ?> · <?= e((string) ($entry['created_at'] ?? '')) ?></small>
                                        <?php if (!empty($entry['note'])): ?><p><?= e((string) $entry['note']) ?></p><?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</section>
