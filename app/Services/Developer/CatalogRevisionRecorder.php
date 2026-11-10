<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Durable, numbered before/after history of catalog changes. Call only inside
 * the subject's existing row-locked transaction; never delete past revisions.
 */
final class CatalogRevisionRecorder
{
    public const PROJECT_FIELDS = [
        'name', 'project_type', 'status', 'completion_status', 'description',
        'location_id', 'address_line', 'latitude', 'longitude',
        'launch_date', 'expected_completion_date', 'completed_at', 'published_at',
    ];

    public const PROPERTY_FIELDS = [
        'reference_code', 'title', 'property_type', 'status', 'description',
        'project_id', 'location_id', 'address_line', 'bedrooms', 'bathrooms',
        'parking_spaces', 'floor_number', 'total_floors', 'area_value',
        'area_unit', 'land_area_value', 'land_area_unit', 'furnishing_status',
        'year_built', 'latitude', 'longitude',
    ];

    public const LISTING_FIELDS = [
        'transaction_type', 'status', 'price', 'currency', 'pricing_period',
        'deposit_amount', 'available_from', 'negotiable', 'published_at',
    ];

    /** @return array<string, mixed> */
    public function snapshot(Model $subject, string $type): array
    {
        $fields = match ($type) {
            'project' => self::PROJECT_FIELDS,
            'property' => self::PROPERTY_FIELDS,
            'listing' => self::LISTING_FIELDS,
            default => throw new InvalidArgumentException('Unknown catalog revision subject.'),
        };

        $snapshot = $subject->only($fields);
        // Date-only legacy fields are calendar dates, not instants. Eloquent
        // JSON encoding of Carbon otherwise converts them to prior-day UTC.
        $dateFields = match ($type) {
            'project' => ['launch_date', 'expected_completion_date', 'completed_at'],
            'listing' => ['available_from'],
            default => [],
        };
        foreach ($dateFields as $field) {
            $date = $subject->getAttribute($field);
            $snapshot[$field] = $date instanceof \\DateTimeInterface
                ? $date->format('Y-m-d')
                : ($date === null ? null : (string) $date);
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function record(
        ?User $actor, string $type, Model $subject, string $event,
        ?array $before, ?array $after,
    ): void {
        if (!in_array($type, ['project', 'property', 'listing'], true)
            || !in_array($event, [
                'created', 'revised', 'submitted', 'approved', 'rejected',
                'unpublished', 'gallery_changed',
            ], true)) {
            throw new InvalidArgumentException('Unsupported catalog revision.');
        }

        // The caller already locks the subject or is creating it inside the
        // same transaction; the unique index rejects duplicate version IDs.
        $number = (int) DB::table('catalog_revisions')
            ->where('subject_type', $type)
            ->where('subject_id', $subject->getKey())
            ->max('revision_number') + 1;

        DB::table('catalog_revisions')->insert([
            'subject_type' => $type,
            'subject_id' => $subject->getKey(),
            'revision_number' => $number,
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'before_snapshot' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_snapshot' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
