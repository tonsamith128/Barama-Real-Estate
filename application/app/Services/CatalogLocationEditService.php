<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use DomainException;
use InvalidArgumentException;
use PDO;

/** Admin-only non-structural location editing; existing hierarchy IDs remain immutable. */
final class CatalogLocationEditService
{
    private function authorize(): array
    {
        $auth = new AuthService();
        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can('location.manage')) {
            throw new DomainException('Administrator location management permission is required.');
        }
        return $auth->user() ?? [];
    }

    public function read(int $id): ?array
    {
        $this->authorize();
        if ($id < 1) {
            return null;
        }
        $record = $this->load(Database::connection(), $id, false);
        if ($record === null) {
            return null;
        }
        $record['revision'] = self::revision($record);
        return $record;
    }

    private function load(PDO $pdo, int $id, bool $lock): ?array
    {
        $sql = 'SELECT l.id, l.uuid, l.name, l.slug, l.type, l.country_code, l.parent_id,
                       l.latitude, l.longitude, l.sort_order, l.status, l.updated_at,
                       parent.name AS parent_name
                FROM locations l LEFT JOIN locations parent ON parent.id=l.parent_id
                WHERE l.id=:id LIMIT 1';
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) ? $result : null;
    }

    private static function revision(array $row): string
    {
        $values = [];
        foreach (['id','name','slug','type','country_code','parent_id','latitude','longitude','sort_order','status','updated_at'] as $column) {
            $values[] = $row[$column] === null ? null : (string) $row[$column];
        }
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    private static function coordinate(array $data, string $field, float $min, float $max): ?string
    {
        $raw = $data[$field] ?? null;
        if (!is_scalar($raw) && $raw !== null) {
            throw new InvalidArgumentException('Invalid ' . $field . '.');
        }
        $value = trim((string) ($raw ?? ''));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[+-]?(?:\d{1,3}(?:\.\d{1,7})?|\.\d{1,7})$/D', $value) !== 1 || !is_finite((float) $value) || (float) $value < $min || (float) $value > $max) {
            throw new InvalidArgumentException(ucfirst($field) . ' is outside its valid range or precision.');
        }
        return number_format((float)$value, 7, '.', '');
    }

    /** Updates only non-structural fields. Admin, CSRF and optimistic revision required. */
    public function update(int $id, array $input): array
    {
        $actor = $this->authorize();
        if ($id < 1) {
            throw new InvalidArgumentException('A valid location ID is required.');
        }
        $name = $input['name'] ?? null;
        if (!is_string($name)) {
            throw new InvalidArgumentException('Enter a valid location name.');
        }
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Location name must be 2 to 160 characters.');
        }
        $lat = self::coordinate($input, 'latitude', -90, 90);
        $lng = self::coordinate($input, 'longitude', -180, 180);
        $order = $input['sort_order'] ?? null;
        if (!is_string($order) && !is_int($order)) {
            throw new InvalidArgumentException('Sort order must be an integer.');
        }
        if (preg_match('/^-?\d{1,10}$/D', (string)$order) !== 1
            || filter_var((string)$order, FILTER_VALIDATE_INT, ['options'=>['min_range'=>-2147483648,'max_range'=>2147483647]]) === false) {
            throw new InvalidArgumentException('Sort order must be a valid integer.');
        }
        $revision = $input['revision'] ?? null;
        if (!is_string($revision) || preg_match('/^[a-f0-9]{64}$/D', $revision) !== 1) {
            throw new InvalidArgumentException('Refresh the edit form before saving.');
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $row = $this->load($pdo, $id, true);
            if ($row === null) {
                throw new InvalidArgumentException('Location not found.');
            }
            if (!hash_equals(self::revision($row), $revision)) {
                throw new DomainException('This location changed since you opened it. Reload the Edit page and try again.');
            }
            $stmt = $pdo->prepare('UPDATE locations SET name=:name, latitude=:latitude,
                longitude=:longitude, sort_order=:sort_order WHERE id=:id');
            $stmt->execute(['name'=>$name, 'latitude'=>$lat, 'longitude'=>$lng, 'sort_order'=>(int)$order, 'id'=>$id]);
            (new SecurityAuditService())->record('catalog.location.updated', (int)($actor['id'] ?? 0) ?: null, null, [
                'location_id'=>$id,
                'fields'=>'name,latitude,longitude,sort_order',
            ]);
            $pdo->commit();
            return ['location_id'=>$id, 'message'=>'Location details updated. Existing hierarchy and linked records were preserved.'];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
