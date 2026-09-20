<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\NotificationRepository;
use Barama\Repositories\SavedSearchAlertRepository;
use Barama\Services\DomainWorkflowService;
use Barama\Services\InquiryService;
use Barama\Services\NotificationService;
use Barama\Services\OutboxDeliveryService;
use Barama\Services\SavedSearchAlertService;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$pdo = Database::connection();
$notifications = new NotificationRepository();
$matches = new SavedSearchAlertRepository();

$userId = static function (string $email) use ($pdo): int {
    $statement = $pdo->prepare(
        'SELECT id FROM users WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $id = $statement->fetchColumn();

    if ($id === false) {
        throw new RuntimeException('Notification smoke user was not found: ' . $email);
    }

    return (int) $id;
};

$customerId = $userId('qa-customer@example.test');
$developerId = $userId('qa-developer@example.test');

$listingStatement = $pdo->prepare(
    'SELECT l.id
     FROM listings l
     INNER JOIN properties p ON p.id = l.property_id
     WHERE p.title = :title
       AND l.status = "published"
     LIMIT 1'
);
$listingStatement->execute(['title' => 'QA Riverside Condominium']);
$listingId = $listingStatement->fetchColumn();

if ($listingId === false) {
    throw new RuntimeException('Published QA listing was not found.');
}

$searchStatement = $pdo->prepare(
    'SELECT id
     FROM saved_searches
     WHERE user_id = :user_id
     ORDER BY id DESC
     LIMIT 1'
);
$searchStatement->execute(['user_id' => $customerId]);
$savedSearchId = $searchStatement->fetchColumn();

if ($savedSearchId === false) {
    throw new RuntimeException('QA saved search was not found.');
}

if ($notifications->unreadCount($developerId) < 1) {
    throw new RuntimeException('Domain workflow did not create company notifications.');
}

if ($notifications->unreadCount($customerId) < 1) {
    throw new RuntimeException('Viewing workflow did not create customer notifications.');
}

$pendingBefore = $notifications->outboxCount('pending');

if ($pendingBefore < 1) {
    throw new RuntimeException('Domain workflow did not queue notification emails.');
}

$notificationService = new NotificationService();
$notificationService->updatePreferences($customerId, [
    'in_app_enabled' => '1',
    'email_enabled' => '1',
    'saved_search_alerts' => '1',
    'inquiry_updates' => '1',
]);

$unreadBeforeSuppressed = $notifications->unreadCount($customerId);
$pendingBeforeSuppressed = $notifications->outboxCount('pending');

$notificationService->notifyUser(
    $customerId,
    'viewing_updates',
    [
        'type' => 'qa_suppressed_viewing',
        'title' => 'Suppressed QA viewing event',
        'message' => 'This should be suppressed by preferences.',
        'action_url' => '/account/viewings',
        'entity_type' => 'viewing',
        'entity_id' => 999999,
        'dedupe_key' => 'qa:suppressed:viewing',
    ]
);

if (
    $notifications->unreadCount($customerId) !== $unreadBeforeSuppressed
    || $notifications->outboxCount('pending') !== $pendingBeforeSuppressed
) {
    throw new RuntimeException('Notification category preference suppression failed.');
}

$notificationService->updatePreferences($customerId, [
    'in_app_enabled' => '1',
    'email_enabled' => '1',
    'saved_search_alerts' => '1',
    'viewing_updates' => '1',
    'inquiry_updates' => '1',
]);

$alertService = new SavedSearchAlertService();
$firstAlertRun = $alertService->process('all', 100, true);
$matchCountAfterFirstRun = $matches->matchCount((int) $savedSearchId);
$secondAlertRun = $alertService->process('all', 100, true);
$matchCountAfterSecondRun = $matches->matchCount((int) $savedSearchId);

if (
    $firstAlertRun['matched'] < 1
    || $firstAlertRun['notified'] < 1
    || $matchCountAfterFirstRun < 1
    || $secondAlertRun['matched'] !== 0
    || $secondAlertRun['notified'] !== 0
    || $matchCountAfterSecondRun !== $matchCountAfterFirstRun
) {
    throw new RuntimeException('Saved-search matching or digest idempotency failed.');
}

Session::put('auth.user_id', $developerId);

$guestInquiryId = (new InquiryService())->capture([
    'website' => '',
    'target_type' => 'listing',
    'target_id' => (int) $listingId,
    'contact_name' => 'QA Guest Contact',
    'contact_email' => 'qa-guest@example.test',
    'preferred_contact' => 'email',
    'message' => 'QA guest inquiry used to verify direct transactional email delivery.',
], '127.0.0.177', null);

$workflow = new DomainWorkflowService();
$workflow->transition('inquiry', $guestInquiryId, 'read', 'QA guest inquiry read');
$workflow->transition('inquiry', $guestInquiryId, 'replied', 'QA guest inquiry replied');

$guestOutbox = $pdo->prepare(
    'SELECT COUNT(*)
     FROM email_outbox
     WHERE to_address = :email
       AND dedupe_key LIKE :dedupe'
);
$guestOutbox->execute([
    'email' => 'qa-guest@example.test',
    'dedupe' => '%inquiry:' . $guestInquiryId . ':replied%',
]);

if ((int) $guestOutbox->fetchColumn() !== 1) {
    throw new RuntimeException('Guest inquiry transactional email was not queued exactly once.');
}

Session::put('auth.user_id', $customerId);

$customerRows = $notifications->notifications($customerId, 100);

if ($customerRows === []) {
    throw new RuntimeException('Customer notification inbox is empty.');
}

$firstUnread = null;

foreach ($customerRows as $row) {
    if ($row['read_at'] === null) {
        $firstUnread = (int) $row['id'];
        break;
    }
}

if ($firstUnread === null) {
    throw new RuntimeException('No unread customer notification was available for read-state QA.');
}

$beforeRead = $notifications->unreadCount($customerId);
$notifications->markRead($customerId, $firstUnread);

if ($notifications->unreadCount($customerId) !== $beforeRead - 1) {
    throw new RuntimeException('Notification mark-read ownership state failed.');
}

$notifications->markAllRead($customerId);

if ($notifications->unreadCount($customerId) !== 0) {
    throw new RuntimeException('Notification mark-all-read failed.');
}

$delivery = (new OutboxDeliveryService())->deliver(500);

if (
    $delivery['processed'] < 1
    || $delivery['sent'] < 1
    || $delivery['failed'] !== 0
    || $notifications->outboxCount('pending') !== 0
    || $notifications->outboxCount('failed') !== 0
) {
    throw new RuntimeException('Notification email outbox delivery failed.');
}

fwrite(
    STDOUT,
    sprintf(
        "[PASS] Notification smoke test: preferences, inbox state, saved-search idempotency, guest updates, and %d outbox delivery(ies) verified.\n",
        $delivery['sent']
    )
);
