<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Amenity;
use App\Models\Company;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CatalogAmenityManager
{
    public function __construct(
        private readonly CompanyCatalogWriter $properties,
        private readonly ProjectManager $projects,
        private readonly CatalogRevisionRecorder $revisions,
    ) {
    }

    /**
     * @param array<int, int> $ids
     */
    public function syncProject(User $actor, Company $company, Project $project, array $ids): void
    {
        $this->projects->assertOwner($actor, $company, $project);
        $this->assertActiveAmenityIds($ids);

        DB::transaction(function () use ($actor, $company, $project, $ids): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $this->projects->assertOwner($actor, $company, $locked);
            abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 409);
            $before = $locked->amenities()->orderBy('amenities.id')->pluck('amenities.id')->map('intval')->all();
            $after = array_values(array_unique($ids));
            sort($after);
            if ($before === $after) {
                return;
            }
            $locked->amenities()->sync($after);
            $this->record($actor, $company, 'project', $locked, $before, $after);
        });
    }

    /**
     * @param array<int, int> $ids
     */
    public function syncProperty(User $actor, Company $company, Property $property, array $ids): void
    {
        $this->properties->assertCanManage($actor, $company);
        abort_unless($property->company_id === $company->getKey(), 404);
        $this->assertActiveAmenityIds($ids);

        DB::transaction(function () use ($actor, $company, $property, $ids): void {
            $locked = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $this->properties->assertCanManage($actor, $company);
            abort_unless($locked->company_id === $company->getKey(), 404);
            abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 409);
            $before = $locked->amenities()->orderBy('amenities.id')->pluck('amenities.id')->map('intval')->all();
            $after = array_values(array_unique($ids));
            sort($after);
            if ($before === $after) {
                return;
            }
            $locked->amenities()->sync($after);
            $this->record($actor, $company, 'property', $locked, $before, $after);
        });
    }

    /** @param array<int, int> $ids */
    private function assertActiveAmenityIds(array $ids): void
    {
        if (count($ids) > 100 || count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'amenity_ids' => 'Choose up to 100 unique amenities.',
            ]);
        }
        if ($ids === []) {
            return;
        }
        $count = Amenity::query()->active()->whereIn('id', $ids)->count();
        if ($count !== count($ids)) {
            throw ValidationException::withMessages([
                'amenity_ids' => 'One or more amenities are unavailable.',
            ]);
        }
    }

    /**
     * @param array<int, int> $before
     * @param array<int, int> $after
     */
    private function record(
        User $actor, Company $company, string $type,
        Project|Property $subject, array $before, array $after,
    ): void {
        $this->revisions->record($actor, $type, $subject, 'amenities_changed',
            ['amenity_ids' => $before], ['amenity_ids' => $after]);
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'developer.'.$type.'.amenities_changed',
            'subject_type' => $type,
            'subject_id' => $subject->getKey(),
            'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
