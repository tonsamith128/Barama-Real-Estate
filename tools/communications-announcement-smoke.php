<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\AnnouncementRepository;
use Barama\Repositories\NotificationRepository;
use Barama\Services\AnnouncementDeliveryService;
use Barama\Services\AnnouncementManagementService;
use Barama\Services\EmailTemplateManagementService;
use Barama\Services\NotificationService;
use DomainException;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$pdo = Database::connection();
$announcements = new AnnouncementRepository();
$notifications = new NotificationRepository();

$userId = static function (string $email) use ($pdo): int {
    $statement = $pdo->prepare(
        'SELECT id FROM users WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $id = $statement->fetchColumn();

    if ($id === false) {
        throw new RuntimeException(
            'Communications announcement QA user was not found: ' . $email
        );
    }

    return (int) $id;
};

$adminId = $userId('qa-admin@example.test');
$customerId = $userId('qa-customer@example.test');

$requiredTables = [
    'communication_announcements',
    'communication_announcement_deliveries',
];

foreach ($requiredTables as $table) {
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $statement->execute(['table_name' => $table]);

    if ((int) $statement->fetchColumn() !== 1) {
        throw new RuntimeException(
            'Required communications table is missing: ' . $table
        );
    }
}

$permissionStatement = $pdo->query(
    'SELECT COUNT(*)
     FROM permissions
     WHERE slug = "communication.manage"'
);

if ((int) $permissionStatement->fetchColumn() !== 1) {
    throw new RuntimeException(
        'communication.manage permission is missing.'
    );
}

Session::put('auth.user_id', $adminId);

$templateService = new EmailTemplateManagementService();
$templateId = $templateService->create([
    'template_key' => 'announcement_published',
    'name' => 'QA announcement template',
    'description' => 'Disposable Communications Phase 7 QA template.',
    'subject_template' => '[QA] {{ title }}',
    'html_template' => '<h1>{{ title }}</h1><p>{{ message }}</p><a href="{{ action_url }}">{{ action_label }}</a><p>{{ recipient_name }}</p>',
    'text_template' => "{{ title }}\n{{ message }}\n{{ action_label }}: {{ action_url }}\n{{ recipient_name }}",
]);
$templateService->setStatus($templateId, 'active');

$notificationService = new NotificationService();
$notificationService->updatePreferences($customerId, [
    'in_app_enabled' => '0',
    'email_enabled' => '0',
    'saved_search_alerts' => '1',
    'viewing_updates' => '1',
    'inquiry_updates' => '1',
]);

$management = new AnnouncementManagementService();
$announcementId = $management->create([
    'title' => 'QA platform announcement',
    'message' => 'Communications Phase 7 audience-delivery verification.',
    'cta_label' => 'Browse properties',
    'cta_url' => '/properties',
    'audience' => 'customer',
    'priority' => 'high',
    'send_in_app' => '1',
    'send_email' => '1',
]);

$management->setStatus($announcementId, 'scheduled', [
    'scheduled_at' => date('Y-m-d\TH:i', time() + 600),
]);
$management->setStatus($announcementId, 'draft');

$management->update($announcementId, [
    'title' => 'QA platform announcement updated',
    'message' => 'Communications Phase 7 audience-delivery verification.',
    'cta_label' => 'Browse properties',
    'cta_url' => '/properties',
    'audience' => 'customer',
    'priority' => 'urgent',
    'send_in_app' => '1',
    'send_email' => '1',
]);

$recipients = $announcements->recipients('customer');

if ($recipients === []) {
    throw new RuntimeException(
        'Customer audience resolution returned no active recipients.'
    );
}

$expectedInApp = 0;
$expectedEmail = 0;
$expectedSkipped = 0;
$emailRecipientId = null;

foreach ($recipients as $recipient) {
    $recipientId = (int) $recipient['id'];
    $preferences = $notifications->preferences($recipientId);
    $canInApp = !empty($preferences['in_app_enabled']);
    $canEmail = !empty($preferences['email_enabled'])
        && filter_var((string) $recipient['email'], FILTER_VALIDATE_EMAIL) !== false;

    if ($canInApp) {
        $expectedInApp++;
    }

    if ($canEmail) {
        $expectedEmail++;
        $emailRecipientId ??= $recipientId;
    }

    if (!$canInApp && !$canEmail) {
        $expectedSkipped++;
    }
}

$result = $management->publishNow($announcementId);

if (
    $result['status'] !== 'published'
    || $result['recipients'] !== count($recipients)
    || $result['in_app'] !== $expectedInApp
    || $result['email'] !== $expectedEmail
    || $result['skipped'] !== $expectedSkipped
) {
    throw new RuntimeException(
        'Announcement audience or channel accounting did not match resolved preferences.'
    );
}

$deliveryCountStatement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM communication_announcement_deliveries
     WHERE announcement_id = :announcement_id'
);
$deliveryCountStatement->execute(['announcement_id' => $announcementId]);

if ((int) $deliveryCountStatement->fetchColumn() !== count($recipients)) {
    throw new RuntimeException(
        'Per-recipient announcement delivery ledger is incomplete.'
    );
}

$disabledDeliveryStatement = $pdo->prepare(
    'SELECT delivery_state, notification_id, email_outbox_id
     FROM communication_announcement_deliveries
     WHERE announcement_id = :announcement_id
       AND user_id = :user_id
     LIMIT 1'
);
$disabledDeliveryStatement->execute([
    'announcement_id' => $announcementId,
    'user_id' => $customerId,
]);
$disabledDelivery = $disabledDeliveryStatement->fetch(PDO::FETCH_ASSOC);

if (
    !is_array($disabledDelivery)
    || (string) $disabledDelivery['delivery_state'] !== 'skipped'
    || $disabledDelivery['notification_id'] !== null
    || $disabledDelivery['email_outbox_id'] !== null
) {
    throw new RuntimeException(
        'Announcement delivery did not respect the customer global channel switches.'
    );
}

if ($emailRecipientId === null) {
    throw new RuntimeException(
        'No email-enabled QA customer was available for template verification.'
    );
}

$emailStatement = $pdo->prepare(
    'SELECT subject, html_body, text_body
     FROM email_outbox
     WHERE user_id = :user_id
       AND dedupe_key = :dedupe_key
     LIMIT 1'
);
$emailStatement->execute([
    'user_id' => $emailRecipientId,
    'dedupe_key' => 'user:' . $emailRecipientId . ':announcement:' . $announcementId,
]);
$emailRow = $emailStatement->fetch(PDO::FETCH_ASSOC);

if (
    !is_array($emailRow)
    || !str_starts_with((string) $emailRow['subject'], '[QA] ')
    || !str_contains((string) $emailRow['html_body'], 'Browse properties')
    || !str_contains((string) $emailRow['text_body'], 'Browse properties:')
) {
    throw new RuntimeException(
        'Active announcement email template was not rendered into the immutable outbox snapshot.'
    );
}

$notificationCountBefore = (int) $pdo->query(
    'SELECT COUNT(*) FROM notifications WHERE category = "announcements"'
)->fetchColumn();
$outboxCountBefore = (int) $pdo->query(
    'SELECT COUNT(*) FROM email_outbox'
)->fetchColumn();
$ledgerCountBefore = (int) $pdo->query(
    'SELECT COUNT(*) FROM communication_announcement_deliveries'
)->fetchColumn();

$repeat = $management->publishNow($announcementId);

if (
    $repeat['status'] !== 'published'
    || $repeat['recipients'] !== 0
    || (int) $pdo->query(
        'SELECT COUNT(*) FROM notifications WHERE category = "announcements"'
    )->fetchColumn() !== $notificationCountBefore
    || (int) $pdo->query(
        'SELECT COUNT(*) FROM email_outbox'
    )->fetchColumn() !== $outboxCountBefore
    || (int) $pdo->query(
        'SELECT COUNT(*) FROM communication_announcement_deliveries'
    )->fetchColumn() !== $ledgerCountBefore
) {
    throw new RuntimeException(
        'Announcement publication idempotency failed.'
    );
}

$publishedEditBlocked = false;

try {
    $management->update($announcementId, [
        'title' => 'Published content must stay immutable',
        'message' => 'This update must be rejected.',
        'audience' => 'customer',
        'priority' => 'normal',
        'send_in_app' => '1',
        'send_email' => '1',
    ]);
} catch (DomainException) {
    $publishedEditBlocked = true;
}

if (!$publishedEditBlocked) {
    throw new RuntimeException(
        'Published announcement content remained editable.'
    );
}

$scheduledId = $management->create([
    'title' => 'QA scheduled platform announcement',
    'message' => 'Scheduled runner verification.',
    'audience' => 'administrator',
    'priority' => 'normal',
    'send_in_app' => '1',
    'send_email' => '0',
]);
$management->setStatus($scheduledId, 'scheduled', [
    'scheduled_at' => date('Y-m-d\TH:i', time() + 600),
]);

$dueStatement = $pdo->prepare(
    'UPDATE communication_announcements
     SET scheduled_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 MINUTE)
     WHERE id = :id'
);
$dueStatement->execute(['id' => $scheduledId]);

$dueResult = (new AnnouncementDeliveryService())->processDue(100);

if ($dueResult['published'] < 1) {
    throw new RuntimeException(
        'Due scheduled announcement was not published by the canonical runner service.'
    );
}

$statusStatement = $pdo->prepare(
    'SELECT status FROM communication_announcements WHERE id = :id'
);
$statusStatement->execute(['id' => $scheduledId]);

if ((string) $statusStatement->fetchColumn() !== 'published') {
    throw new RuntimeException(
        'Scheduled announcement did not reach published state.'
    );
}

$auditStatement = $pdo->query(
    'SELECT COUNT(*)
     FROM security_audit_events
     WHERE event_type IN (
        "admin.communication_announcement_created",
        "admin.communication_announcement_updated",
        "admin.communication_announcement_status_changed",
        "admin.communication_announcement_published",
        "system.communication_announcement_published"
     )'
);

if ((int) $auditStatement->fetchColumn() < 7) {
    throw new RuntimeException(
        'Expected announcement security audit evidence was not recorded.'
    );
}

fwrite(
    STDOUT,
    sprintf(
        "[PASS] Communications announcement smoke: %d customer recipient(s), %d in-app, %d email, %d skipped; lifecycle, templates, idempotency, scheduled delivery, immutable publication and audit evidence verified.\n",
        count($recipients),
        $expectedInApp,
        $expectedEmail,
        $expectedSkipped
    )
);
