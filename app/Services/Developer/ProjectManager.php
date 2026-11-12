<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProjectManager
{
    public function __construct(
        private readonly CompanyCatalogWriter $permissions,
        private readonly CatalogLocationPolicy $locations,
        private readonly CatalogRevisionRecorder $revisions,
    ) {
    }

    public function assertOwnerAccess(User $user, Company $company): void
    {
        $this->permissions->assertCanManage($user, $company);
    }

    public function assertOwner(User $user, Company $company, Project $project): void
    {
        $this->permissions->assertCanManage($user, $company);
        abort_unless($project->company_id === $company->getKey(), 404);
    }

    public function create(User $user, Company $company, array $data): Project
    {
        $this->permissions->assertCanManage($user, $company);
        $this->locations->assertSelectable(isset($data['location_id']) ? (int) $data['location_id'] : null);

        return DB::transaction(function () use ($user, $company, $data): Project {
            $this->permissions->assertCanManage($user, $company);
            $project = Project::query()->create([
                'company_id' => $company->getKey(),
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(10)),
                'project_type' => $data['project_type'],
                'completion_status' => $data['completion_status'] ?? null,
                'description' => $data['description'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'address_line' => $data['address_line'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'launch_date' => $data['launch_date'] ?? null,
                'expected_completion_date' => $data['expected_completion_date'] ?? null,
                'completed_at' => $data['completed_at'] ?? null,
                'status' => 'draft',
                'published_at' => null,
            ]);
            $this->revisions->record($user, 'project', $project, 'created', null,
                $this->revisions->snapshot($project, 'project'));
            $this->audit($user, 'developer.project.created', $project);
            return $project;
        });
    }

    public function revise(User $user, Company $company, Project $project, array $data): void
    {
        $this->assertOwner($user, $company, $project);
        $this->locations->assertSelectable(isset($data['location_id']) ? (int) $data['location_id'] : null);

        DB::transaction(function () use ($user, $company, $project, $data): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $this->assertOwner($user, $company, $locked);
            abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 409);
            $before = $this->revisions->snapshot($locked, 'project');

            $locked->update([
                'name' => $data['name'],
                'project_type' => $data['project_type'],
                'completion_status' => $data['completion_status'] ?? null,
                'description' => $data['description'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'address_line' => $data['address_line'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'launch_date' => $data['launch_date'] ?? null,
                'expected_completion_date' => $data['expected_completion_date'] ?? null,
                'completed_at' => $data['completed_at'] ?? null,
                'status' => 'draft',
                'published_at' => null,
            ]);
            $this->revisions->record($user, 'project', $locked, 'revised', $before,
                $this->revisions->snapshot($locked, 'project'));
            $this->audit($user, 'developer.project.revised', $locked);
        });
    }

    public function submit(User $user, Company $company, Project $project): void
    {
        $this->assertOwner($user, $company, $project);
        DB::transaction(function () use ($user, $company, $project): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $this->assertOwner($user, $company, $locked);
            abort_unless($locked->status === 'draft', 409);
            $this->locations->assertSelectable($locked->location_id !== null
                ? (int) $locked->location_id : null);
            $before = $this->revisions->snapshot($locked, 'project');
            $locked->update(['status' => 'pending', 'published_at' => null]);
            $this->revisions->record($user, 'project', $locked, 'submitted', $before,
                $this->revisions->snapshot($locked, 'project'));
            $this->audit($user, 'developer.project.submitted', $locked);
        });
    }

    private function audit(User $user, string $action, Project $project): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $user->getKey(),
            'action' => $action,
            'subject_type' => 'project',
            'subject_id' => $project->getKey(),
            'context' => json_encode(['company_id' => $project->company_id], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
