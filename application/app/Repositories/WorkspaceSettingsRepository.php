<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class WorkspaceSettingsRepository
{
    /** @return array<string,mixed> */
    public function get(int $userId, string $workspace): array
    {
        $statement = Database::connection()->prepare(
            'SELECT settings_json FROM workspace_preferences WHERE user_id = :user_id AND workspace = :workspace LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'workspace' => $workspace]);
        $json = $statement->fetchColumn();

        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $settings */
    public function save(int $userId, string $workspace, array $settings): void
    {
        $json = json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $json = '{}';
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO workspace_preferences (user_id, workspace, settings_json)
             VALUES (:user_id, :workspace, :settings_json)
             ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'user_id' => $userId,
            'workspace' => $workspace,
            'settings_json' => $json,
        ]);
    }
}
