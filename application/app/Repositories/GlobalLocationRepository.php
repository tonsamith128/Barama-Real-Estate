<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;
use RuntimeException;

final class GlobalLocationRepository
{
    /* BARAMA_GLOBAL_LOCATIONS_V110 */
    public const MODE_SELECTED = 'selected';
    public const MODE_GLOBAL = 'global';

    public function mode(): string
    {
        $statement = Database::connection()->prepare(
            'SELECT setting_value FROM application_settings WHERE setting_key = :setting_key LIMIT 1'
        );
        $statement->execute(['setting_key' => 'locations.mode']);
        $value = strtolower(trim((string) $statement->fetchColumn()));

        return in_array($value, [self::MODE_SELECTED, self::MODE_GLOBAL], true)
            ? $value
            : self::MODE_SELECTED;
    }

    public function setMode(string $mode): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO application_settings (setting_key, setting_value, value_type) '
            . 'VALUES (:setting_key, :setting_value, :value_type) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type)'
        );
        $statement->execute([
            'setting_key' => 'locations.mode',
            'setting_value' => $mode,
            'value_type' => 'string',
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function countries(): array
    {
        $sql = <<<'SQL'
SELECT
    c.country_code,
    c.iso3,
    c.name,
    c.native_name,
    c.enabled,
    c.is_default,
    c.sort_order,
    c.data_status,
    COALESCE(l.total_places, 0) AS total_places,
    COALESCE(l.active_places, 0) AS active_places
FROM location_countries c
LEFT JOIN (
    SELECT
        UPPER(country_code) AS country_code,
        COUNT(*) AS total_places,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_places
    FROM locations
    WHERE country_code IS NOT NULL
      AND country_code <> ''
    GROUP BY UPPER(country_code)
) l ON l.country_code = c.country_code
ORDER BY c.is_default DESC, c.enabled DESC, c.sort_order ASC, c.name ASC
SQL;

        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function country(string $countryCode): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT country_code, iso3, name, native_name, enabled, is_default, sort_order, data_status '
            . 'FROM location_countries WHERE country_code = :country_code LIMIT 1'
        );
        $statement->execute(['country_code' => strtoupper(trim($countryCode))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function enabledCount(): int
    {
        return (int) Database::connection()->query(
            'SELECT COUNT(*) FROM location_countries WHERE enabled = 1'
        )->fetchColumn();
    }

    public function setCountryEnabled(string $countryCode, bool $enabled): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE location_countries SET enabled = :enabled WHERE country_code = :country_code'
        );
        $statement->execute([
            'enabled' => $enabled ? 1 : 0,
            'country_code' => strtoupper(trim($countryCode)),
        ]);

        if ($statement->rowCount() < 1 && $this->country($countryCode) === null) {
            throw new RuntimeException('Country was not found.');
        }
    }

    public function setDefaultCountry(string $countryCode): void
    {
        $countryCode = strtoupper(trim($countryCode));
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $pdo->exec('UPDATE location_countries SET is_default = 0');
            $statement = $pdo->prepare(
                'UPDATE location_countries '
                . 'SET is_default = 1, enabled = 1 '
                . 'WHERE country_code = :country_code'
            );
            $statement->execute(['country_code' => $countryCode]);

            if ($statement->rowCount() < 1 && $this->country($countryCode) === null) {
                throw new RuntimeException('Country was not found.');
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string,int|string> */
    public function summary(): array
    {
        $pdo = Database::connection();
        $countries = (int) $pdo->query('SELECT COUNT(*) FROM location_countries')->fetchColumn();
        $enabled = (int) $pdo->query('SELECT COUNT(*) FROM location_countries WHERE enabled = 1')->fetchColumn();
        $places = (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn();
        $activePlaces = (int) $pdo->query("SELECT COUNT(*) FROM locations WHERE status = 'active'")->fetchColumn();
        $default = (string) $pdo->query(
            'SELECT country_code FROM location_countries WHERE is_default = 1 LIMIT 1'
        )->fetchColumn();

        $hierarchyRecords = 0;
        $packCountries = 0;

        try {
            $hierarchyRecords = (int) $pdo->query(
                'SELECT COUNT(*) FROM location_hierarchy_registry'
            )->fetchColumn();
            $packCountries = (int) $pdo->query(
                "SELECT COUNT(*) FROM location_data_packs WHERE status IN ('installed', 'country_only')"
            )->fetchColumn();
        } catch (\Throwable $exception) {
            // v1.0.1 compatibility before hierarchy-pack migration is installed.
        }

        return [
            'countries' => $countries,
            'enabled_countries' => $enabled,
            'places' => $places,
            'active_places' => $activePlaces,
            'default_country' => $default,
            'mode' => $this->mode(),
            'hierarchy_records' => $hierarchyRecords,
            'pack_countries' => $packCountries,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function countryTree(string $countryCode): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, uuid, parent_id, type, country_code, name, slug, latitude, longitude, status, sort_order '
            . 'FROM locations WHERE UPPER(country_code) = :country_code '
            . 'ORDER BY COALESCE(parent_id, 0) ASC, sort_order ASC, name ASC'
        );
        $statement->execute(['country_code' => strtoupper(trim($countryCode))]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
