<?php

declare(strict_types=1);

namespace Barama\Support;

use PDO;
use Throwable;
use RuntimeException;

/**
 * Fail-closed check for the two append-only email audit triggers.
 *
 * This is a disposable DDL probe, not a grant parser: server binlog policy,
 * active MySQL roles, and MariaDB variants can invalidate SHOW GRANTS guesses.
 * A non-temporary table is required because MySQL does not support triggers
 * on TEMPORARY tables. The probe drops its private table in a finally block.
 */
final class TriggerCapabilityProbe
{
    public static function assertSupported(PDO $pdo): void
    {
        $suffix = bin2hex(random_bytes(6));
        $table = 'barama_trigger_probe_' . $suffix;
        $updateTrigger = 'barama_probe_update_' . $suffix;
        $deleteTrigger = 'barama_probe_delete_' . $suffix;
        $created = false;
        $failure = null;
        $cleanupFailure = null;

        try {
            $pdo->exec(
                'CREATE TABLE `' . $table . '` (id INT UNSIGNED NOT NULL PRIMARY KEY) '
                . 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $created = true;

            foreach ([
                [$updateTrigger, 'UPDATE'],
                [$deleteTrigger, 'DELETE'],
            ] as [$name, $event]) {
                $pdo->exec(
                    'CREATE TRIGGER `' . $name . '` BEFORE ' . $event
                    . ' ON `' . $table . '` FOR EACH ROW '
                    . "SIGNAL SQLSTATE '45000' "
                    . "SET MESSAGE_TEXT = 'append-only audit'"
                );
            }
        } catch (Throwable $exception) {
            $failure = $exception;
        } finally {
            if ($created) {
                try {
                    // DROP TABLE also removes both test triggers.
                    $pdo->exec('DROP TABLE `' . $table . '`');
                } catch (Throwable $exception) {
                    $cleanupFailure = $exception;
                }
            }
        }

        if ($cleanupFailure !== null) {
            throw new RuntimeException(
                'Database compatibility test could not clean up its temporary-name '
                . 'table (' . $table . '). Ask the host to remove this table and '
                . 'enable CREATE, DROP and TRIGGER privileges before retrying.',
                0,
                $cleanupFailure
            );
        }

        if ($failure !== null) {
            throw new RuntimeException(
                'Database hosting is not compatible with the required append-only '
                . 'email audit triggers. The Barama database account needs CREATE, '
                . 'DROP and TRIGGER permissions. On MySQL servers with binary logging, '
                . 'the host may also need to enable log_bin_trust_function_creators '
                . 'or use an appropriately privileged database account. '
                . 'Ask your hosting provider to enable these capabilities; '
                . 'do not disable audit triggers. No Barama schema migration was applied.',
                0,
                $failure
            );
        }
    }
}
