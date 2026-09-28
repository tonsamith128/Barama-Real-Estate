<?php

declare(strict_types=1);

$webroot = rtrim((string) getenv('WEBROOT'), '/');

if ($webroot === '' || !is_dir($webroot)) {
    fwrite(STDERR, "ERROR: WEBROOT is missing or invalid.\n");
    exit(1);
}

define('BARAMA_CLI', true);
require $webroot . '/application/bootstrap/app.php';

$pdo = \Barama\Core\Database::connection();

$tableCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'contact_messages'"
)->fetchColumn();

if ($tableCount !== 1) {
    fwrite(STDERR, "ERROR: contact_messages table is missing.\n");
    exit(1);
}

$columns = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'contact_messages'
       AND COLUMN_NAME IN (
           'id','uuid','user_id','contact_name','contact_email','contact_phone',
           'subject','message','source','status','ip_hash','user_agent_hash',
           'handled_by_user_id','resolved_at','created_at','updated_at'
       )"
)->fetchColumn();

if ($columns !== 16) {
    fwrite(STDERR, "ERROR: contact_messages schema is incomplete ({$columns}/16 columns).\n");
    exit(1);
}

$migrationStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM schema_migrations WHERE migration = :migration'
);
$migrationStatement->execute([
    'migration' => '0022_create_contact_messages.php',
]);

if ((int) $migrationStatement->fetchColumn() !== 1) {
    fwrite(STDERR, "ERROR: migration 0022_create_contact_messages.php is not recorded.\n");
    exit(1);
}

$uuidIndex = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'contact_messages'
       AND INDEX_NAME = 'contact_messages_uuid_unique'
       AND NON_UNIQUE = 0"
)->fetchColumn();

if ($uuidIndex !== 1) {
    fwrite(STDERR, "ERROR: contact_messages UUID unique index is missing.\n");
    exit(1);
}

fwrite(STDOUT, "DATABASE_CONTACT_MESSAGES: PASS\n");
