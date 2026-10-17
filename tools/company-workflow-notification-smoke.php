<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/CompanyWorkflowNotificationService.php';
require_once dirname(__DIR__) . '/application/app/Http/Controllers/NotificationActionController.php';

use Barama\Http\Controllers\NotificationActionController;
use Barama\Services\CompanyWorkflowNotificationService as WorkflowNotification;

$cases = [
    ['pending', 'workflow_reviews', 'Company awaiting approval', '/dashboard/manage/company/31'],
    ['approved', 'workflow_updates', 'Your company is approved', '/developer/company'],
    ['rejected', 'workflow_updates', 'Company changes requested', '/developer/company'],
];
foreach ($cases as [$status, $category, $title, $url]) {
    $payload = WorkflowNotification::payload(31, 'USA Real Estate', $status, 531);
    if ($payload === null
        || $payload['category'] !== $category
        || $payload['title'] !== $title
        || $payload['action_url'] !== $url
        || $payload['entity_type'] !== 'company'
        || $payload['entity_id'] !== 31
        || $payload['dedupe_key'] !== 'company-workflow:31:531'
    ) {
        fwrite(STDERR, 'FAIL: company ' . $status . ' notification payload' . PHP_EOL);
        exit(1);
    }
    echo 'PASS company ' . $status . ' notification payload' . PHP_EOL;
}

if (WorkflowNotification::payload(31, 'USA Real Estate', 'draft', 531) !== null
    || WorkflowNotification::payload(0, 'USA Real Estate', 'pending', 531) !== null
    || WorkflowNotification::payload(31, 'USA Real Estate', 'pending', 0) !== null) {
    fwrite(STDERR, 'FAIL: invalid notifications must be omitted' . PHP_EOL);
    exit(1);
}

$destinations = [
    ['/dashboard/manage/company/31', true],
    ['/dashboard/approvals?type=company', true],
    ['/developer/company', true],
    ['/dashboard/manage/company/31/delete', false],
    ['/dashboard/manage/listing/31', false],
    ['//evil.example.test/path', false],
    ['/logout', false],
    ['/dashboard/manage/company/%2f31', false],
];
foreach ($destinations as [$destination, $expected]) {
    $accepted = NotificationActionController::safeDestination($destination) === $destination;
    if ($accepted !== $expected) {
        fwrite(STDERR, 'FAIL notification destination: ' . $destination . PHP_EOL);
        exit(1);
    }
    echo 'PASS notification destination: ' . $destination . PHP_EOL;
}

echo 'COMPANY_WORKFLOW_NOTIFICATIONS=PASS' . PHP_EOL;
