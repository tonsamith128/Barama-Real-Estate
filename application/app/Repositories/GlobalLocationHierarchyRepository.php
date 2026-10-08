<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class GlobalLocationHierarchyRepository
{
    /* BARAMA_GLOBAL_LOCATION_HIERARCHY_V110 */

    /** @return array<int,array<string,mixed>> */
    public function selectableCountries(): array
    {
        $mode = (new GlobalLocationRepository())->mode();
        $where = $mode === GlobalLocationRepository::MODE_GLOBAL ? '' : ' WHERE enabled = 1';
        $sql = 'SELECT country_code, iso3, name, native_name, enabled, is_default, data_status '
            . 'FROM location_countries' . $where
            . ' ORDER BY is_default DESC, sort_order ASC, name ASC';

        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function children(string $countryCode, ?int $parentId): array
    {
        $countryCode = strtoupper(trim($countryCode));
        if (!$this->countryAllowed($countryCode)) {
            return [];
        }

        $pdo = Database::connection();
        if ($parentId !== null) {
            $parent = $pdo->prepare(
                'SELECT id FROM locations WHERE id = :id AND UPPER(country_code) = :country_code '
                . "AND status = 'active' LIMIT 1"
            );
            $parent->execute(['id' => $parentId, 'country_code' => $countryCode]);
            if ($parent->fetchColumn() === false) {
                return [];
            }

            $statement = $pdo->prepare(
                'SELECT id, parent_id, type, country_code, name, slug '
                . 'FROM locations WHERE parent_id = :parent_id '
                . 'AND UPPER(country_code) = :country_code AND status = :status '
                . 'ORDER BY sort_order ASC, name ASC LIMIT 1000'
            );
            $statement->execute([
                'parent_id' => $parentId,
                'country_code' => $countryCode,
                'status' => 'active',
            ]);
        } else {
            $statement = $pdo->prepare(
                'SELECT id, parent_id, type, country_code, name, slug '
                . 'FROM locations WHERE parent_id IS NULL '
                . 'AND UPPER(country_code) = :country_code AND status = :status '
                . 'ORDER BY sort_order ASC, name ASC LIMIT 1000'
            );
            $statement->execute([
                'country_code' => $countryCode,
                'status' => 'active',
            ]);
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{country_code:string,path:array<int,array<string,mixed>>}|null */
    public function selection(int $locationId): ?array
    {
        if ($locationId < 1) {
            return null;
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'SELECT id, parent_id, type, country_code, name, slug, status '
            . 'FROM locations WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $locationId]);
        $current = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($current) || (string) ($current['status'] ?? '') !== 'active') {
            return null;
        }

        $countryCode = strtoupper(trim((string) ($current['country_code'] ?? '')));
        if (!$this->countryAllowed($countryCode)) {
            // Existing records may point to a disabled country. The picker may still
            // display that saved path, but new country choices come from policy.
            $country = (new GlobalLocationRepository())->country($countryCode);
            if ($country === null) {
                return null;
            }
        }

        $path = [];
        $seen = [];
        for ($depth = 0; $depth < 16 && is_array($current); $depth++) {
            $id = (int) ($current['id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                break;
            }
            $seen[$id] = true;
            array_unshift($path, [
                'id' => $id,
                'parent_id' => $current['parent_id'] !== null ? (int) $current['parent_id'] : null,
                'type' => (string) ($current['type'] ?? 'area'),
                'country_code' => strtoupper((string) ($current['country_code'] ?? '')),
                'name' => (string) ($current['name'] ?? ''),
                'slug' => (string) ($current['slug'] ?? ''),
            ]);

            $parentId = $current['parent_id'] !== null ? (int) $current['parent_id'] : 0;
            if ($parentId < 1) {
                break;
            }
            $parent = $pdo->prepare(
                'SELECT id, parent_id, type, country_code, name, slug, status '
                . 'FROM locations WHERE id = :id LIMIT 1'
            );
            $parent->execute(['id' => $parentId]);
            $row = $parent->fetch(PDO::FETCH_ASSOC);
            $current = is_array($row) ? $row : null;
        }

        return ['country_code' => $countryCode, 'path' => $path];
    }

    public function countryAllowed(string $countryCode): bool
    {
        $countryCode = strtoupper(trim($countryCode));
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return false;
        }

        $global = new GlobalLocationRepository();
        $country = $global->country($countryCode);
        if ($country === null) {
            return false;
        }

        return $global->mode() === GlobalLocationRepository::MODE_GLOBAL
            || (int) ($country['enabled'] ?? 0) === 1;
    }
}
