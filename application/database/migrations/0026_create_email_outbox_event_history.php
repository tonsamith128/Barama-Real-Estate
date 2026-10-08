<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    // MySQL/MariaDB DDL commits implicitly. Fail before CREATE TABLE when
    // customer hosting cannot preserve the required DB-level immutability.
    // Already-installed triggers need no additional privilege test on replay.
    $existingTriggers = $pdo->query(<<<'SQL'
SELECT COUNT(DISTINCT trigger_name)
FROM information_schema.triggers
WHERE trigger_schema = DATABASE()
  AND event_object_table = 'email_outbox_events'
  AND trigger_name IN (
      'email_outbox_events_no_update',
      'email_outbox_events_no_delete'
  )
SQL)->fetchColumn();

    if ((int) $existingTriggers !== 2) {
        \Barama\Support\TriggerCapabilityProbe::assertSupported($pdo);
    }

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS email_outbox_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email_outbox_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    attempt_number SMALLINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    status_from VARCHAR(24) NULL,
    status_to VARCHAR(24) NULL,
    error_text VARCHAR(1000) NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY email_outbox_events_message_time_index (email_outbox_id, created_at, id),
    KEY email_outbox_events_type_index (event_type, created_at),
    KEY email_outbox_events_actor_index (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $triggerExists = static function (string $name) use ($pdo): bool {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.triggers '
            . 'WHERE trigger_schema = DATABASE() AND trigger_name = :name'
        );
        $statement->execute(['name' => $name]);
        return (int) $statement->fetchColumn() === 1;
    };

    if (!$triggerExists('email_outbox_events_no_update')) {
        $pdo->exec(<<<'SQL'
CREATE TRIGGER email_outbox_events_no_update
BEFORE UPDATE ON email_outbox_events
FOR EACH ROW
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'email_outbox_events is append-only'
SQL);
    }

    if (!$triggerExists('email_outbox_events_no_delete')) {
        $pdo->exec(<<<'SQL'
CREATE TRIGGER email_outbox_events_no_delete
BEFORE DELETE ON email_outbox_events
FOR EACH ROW
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'email_outbox_events is append-only'
SQL);
    }

    $pdo->exec(<<<'SQL'
INSERT INTO email_outbox_events
    (email_outbox_id, event_type, attempt_number, status_to, metadata_json, created_at)
SELECT
    o.id,
    'history_tracking_started',
    o.attempts,
    o.status,
    '{"history_scope":"forward_only","note":"Immutable attempt history starts with migration 0024; earlier attempt-level events were not reconstructed."}',
    CURRENT_TIMESTAMP
FROM email_outbox o
WHERE NOT EXISTS (
    SELECT 1
    FROM email_outbox_events e
    WHERE e.email_outbox_id = o.id
      AND e.event_type = 'history_tracking_started'
)
SQL);
};
