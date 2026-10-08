<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class PaymentMethodRepository
{
    /* BARAMA_PAYMENT_METHODS_V120 */

    /** @return array<int,array<string,mixed>> */
    public function allAdmin(): array
    {
        $rows = Database::connection()->query(
            'SELECT id, uuid, code, name, method_type, handler_key, status,
                    is_configured, sort_order, public_config_json,
                    CASE WHEN secret_config_ciphertext IS NULL
                              OR secret_config_ciphertext = ""
                         THEN 0 ELSE 1 END AS has_secrets,
                    created_at, updated_at
             FROM billing_payment_methods
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            $decoded = json_decode((string) ($row['public_config_json'] ?? '{}'), true);
            $row['public_config'] = is_array($decoded) ? $decoded : [];
            $row['is_configured'] = (int) ($row['is_configured'] ?? 0) === 1;
            $row['has_secrets'] = (int) ($row['has_secrets'] ?? 0) === 1;
            return $row;
        }, $rows);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM billing_payment_methods WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $decoded = json_decode((string) ($row['public_config_json'] ?? '{}'), true);
        $row['public_config'] = is_array($decoded) ? $decoded : [];
        return $row;
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $row = Database::connection()->query(
            'SELECT COUNT(*) AS total,
                    SUM(status = "active") AS active,
                    SUM(method_type = "automatic") AS automatic,
                    SUM(method_type = "manual") AS manual,
                    SUM(is_configured = 1) AS configured
             FROM billing_payment_methods'
        )->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['total'=>0,'active'=>0,'automatic'=>0,'manual'=>0,'configured'=>0];
        }

        return array_map(static fn (mixed $value): int => (int) ($value ?? 0), $row);
    }

    /** @return array<int,array<string,mixed>> */
    public function enabled(): array
    {
        $rows = Database::connection()->query(
            'SELECT id, uuid, code, name, method_type, handler_key, sort_order,
                    public_config_json
             FROM billing_payment_methods
             WHERE status = "active" AND is_configured = 1
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            $decoded = json_decode((string) ($row['public_config_json'] ?? '{}'), true);
            $row['public_config'] = is_array($decoded) ? $decoded : [];
            return $row;
        }, $rows);
    }

    public function codeExists(string $code): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM billing_payment_methods WHERE code = :code LIMIT 1'
        );
        $statement->execute(['code' => $code]);
        return $statement->fetchColumn() !== false;
    }

    public function nextSortOrder(): int
    {
        $value = Database::connection()->query(
            'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM billing_payment_methods'
        )->fetchColumn();
        return max(10, (int) $value);
    }

    /** @param array<string,mixed> $data */
    public function createManual(array $data): int
    {
        $json = json_encode(
            $data['public_config'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($json)) {
            throw new \RuntimeException('Manual payment configuration encoding failed.');
        }
        $statement = Database::connection()->prepare(
            'INSERT INTO billing_payment_methods
                (uuid, code, name, method_type, handler_key, status,
                 is_configured, sort_order, public_config_json,
                 created_by_user_id, updated_by_user_id)
             VALUES
                (UUID(), :code, :name, "manual", "manual_custom", "inactive",
                 1, :sort_order, :public_config, :actor_id, :actor_id)'
        );
        $statement->execute([
            'code' => $data['code'],
            'name' => $data['name'],
            'sort_order' => $data['sort_order'],
            'public_config' => $json,
            'actor_id' => $data['actor_id'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public function setStatus(int $id, string $status, int $actorId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE billing_payment_methods
             SET status = :status,
                 updated_by_user_id = :actor_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }

    /** @param array<string,mixed> $publicConfig */
    public function updateConfiguration(
        int $id,
        array $publicConfig,
        ?string $secretCiphertext,
        bool $configured,
        int $actorId
    ): void {
        $json = json_encode(
            $publicConfig,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (!is_string($json)) {
            throw new \RuntimeException('Payment method configuration encoding failed.');
        }

        if ($secretCiphertext !== null) {
            $statement = Database::connection()->prepare(
                'UPDATE billing_payment_methods
                 SET public_config_json = :public,
                     secret_config_ciphertext = :secret,
                     is_configured = :configured,
                     updated_by_user_id = :actor_id,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $statement->execute([
                'public' => $json,
                'secret' => $secretCiphertext,
                'configured' => $configured ? 1 : 0,
                'actor_id' => $actorId,
                'id' => $id,
            ]);
            return;
        }

        $statement = Database::connection()->prepare(
            'UPDATE billing_payment_methods
             SET public_config_json = :public,
                 is_configured = :configured,
                 updated_by_user_id = :actor_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'public' => $json,
            'configured' => $configured ? 1 : 0,
            'actor_id' => $actorId,
            'id' => $id,
        ]);
    }
}
