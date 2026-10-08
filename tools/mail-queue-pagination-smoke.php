<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Repositories\NotificationRepository;

define('BARAMA_CLI', true);
require dirname(__DIR__) . '/application/bootstrap/app.php';

// Disposable QA database fixture; never execute against production.
if (getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "[FAIL] Mail Queue pagination smoke requires APP_ENV=testing.\n");
    exit(1);
}

$pdo = Database::connection();
$repo = new NotificationRepository();
$token = bin2hex(random_bytes(6));
$uniqueAddress = 'mail-queue-' . $token . '@example.test';
$prefix = 'Pagination QA ' . $token;

try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        'INSERT INTO email_outbox '
        . '(uuid, to_address, subject, html_body, text_body, status, dedupe_key, available_at) '
        . 'VALUES (:uuid, :address, :subject, :html, :text, :status, :dedupe, CURRENT_TIMESTAMP)'
    );
    for ($n = 1; $n <= 23; $n++) {
        $identifier = $token . '-' . $n;
        $insert->execute([
            'uuid' => sprintf('33333333-4444-4555-8666-%012d', $n),
            'address' => $uniqueAddress,
            'subject' => $prefix . ' ' . $n,
            'html' => '<p>Testing only</p>',
            'text' => 'Testing only',
            'status' => 'pending',
            'dedupe' => 'queue-pagination-' . $identifier,
        ]);
    }

    $filters = ['recipient' => $uniqueAddress];
    $first = $repo->adminOutboxPage($filters, 1, 100);
    $second = $repo->adminOutboxPage($filters, 2, 25);
    $third = $repo->adminOutboxPage($filters, 3, 10);
    $outOfRange = $repo->adminOutboxPage($filters, 999, 50);

    if ($first['total'] !== 23 || $first['pages'] !== 3
        || $first['per_page'] !== 10 || count($first['rows']) !== 10
        || $second['page'] !== 2 || count($second['rows']) !== 10
        || $third['page'] !== 3 || count($third['rows']) !== 3
        || $outOfRange['page'] !== 3) {
        throw new RuntimeException('Ten-row pagination, page clamp, or count contract failed.');
    }

    $firstIds = array_column($first['rows'], 'id');
    $secondIds = array_column($second['rows'], 'id');
    if (array_intersect($firstIds, $secondIds) !== []) {
        throw new RuntimeException('Mail Queue pagination returned duplicate records.');
    }

    $view = (string) file_get_contents(
        dirname(__DIR__) . '/application/resources/views/workspace/communications-mail-queue.php'
    );
    if (!str_contains($view, 'name="page"')
        || !str_contains($view, 'mail-queue-pagination__goto')
        || !str_contains($view, 'name="per_page" value="10"')
        || !str_contains($view, 'Pending means queued, not sent.')) {
        throw new RuntimeException('Mail Queue navigation and pending guidance are incomplete.');
    }

    echo "[PASS] 23 fixture messages span 10, 10 and 3 rows with no overlap.\n";
    echo "[PASS] Go to navigation and pending explanation are included.\n";
    echo "MAIL_QUEUE_PAGINATION_QA=PASS\n";
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] Mail Queue pagination: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
