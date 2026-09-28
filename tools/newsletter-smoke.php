<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Repositories\NewsletterSubscriberRepository;
use Barama\Services\NewsletterSubscriptionService;
use InvalidArgumentException;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$pdo = Database::connection();
$tableExists = $pdo->query(
    'SHOW TABLES LIKE "newsletter_subscribers"'
)->fetchColumn();

if (!is_string($tableExists) || $tableExists !== 'newsletter_subscribers') {
    throw new RuntimeException('Newsletter subscriber table is missing.');
}

$pdo->beginTransaction();

try {
    $service = new NewsletterSubscriptionService();

    $firstId = $service->subscribe([
        'website' => '',
        'email' => 'newsletter-smoke@example.test',
        'source' => 'qa-homepage',
    ], '127.0.0.201');

    $secondId = $service->subscribe([
        'website' => '',
        'email' => 'NEWSLETTER-SMOKE@example.test',
        'source' => 'qa-repeat',
    ], '127.0.0.201');

    if ($firstId < 1 || $secondId !== $firstId) {
        throw new RuntimeException('Newsletter duplicate-safe upsert did not preserve the subscriber id.');
    }

    $statement = $pdo->prepare(
        'SELECT email, status, source
         FROM newsletter_subscribers
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $firstId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (
        !is_array($row)
        || $row['email'] !== 'newsletter-smoke@example.test'
        || $row['status'] !== 'active'
        || $row['source'] !== 'qa-repeat'
    ) {
        throw new RuntimeException('Newsletter subscriber state is incorrect after duplicate subscribe.');
    }

    $invalidRejected = false;

    try {
        $service->subscribe([
            'website' => '',
            'email' => 'not-an-email',
            'source' => 'qa-invalid',
        ], '127.0.0.202');
    } catch (InvalidArgumentException) {
        $invalidRejected = true;
    }

    if (!$invalidRejected) {
        throw new RuntimeException('Invalid newsletter email was not rejected.');
    }

    if ((new NewsletterSubscriberRepository())->activeCount() < 1) {
        throw new RuntimeException('Newsletter active subscriber count is incorrect.');
    }

    $pdo->rollBack();

    fwrite(
        STDOUT,
        json_encode([
            'ok' => true,
            'newsletter_subscribe' => true,
            'duplicate_safe' => true,
            'invalid_email_rejected' => true,
            'transaction_rolled_back' => true,
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}
