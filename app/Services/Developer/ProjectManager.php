<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProjectManager
{
    public function __construct(private readonly CompanyCatalogWriter $permissions)
    {
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

        return DB::transaction(function () use ($user, $company, $data): Project {
            $project = Project::query()->create([
                'company_id' => $company->getKey(),
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(10)),
                'project_type' => $data['project_type'],
                'completion_status' => $data['completion_status'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'published_at' => null,
            ]);
            $this->audit($user, 'developer.project.created', $project);
            return $project;
        });
    }

    public function revise(User $user, Company $company, Project $project, array $data): void
    {
        $this->assertOwner($user, $company, $project);
        DB::transaction(function () use ($user, $project, $data): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 409);
            $locked->update([
                'name' => $data['name'],
                'project_type' => $data['project_type'],
                'completion_status' => $data['completion_status'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'published_at' => null,
            ]);
            $this->audit($user, 'developer.project.revised', $locked);
        });
    }

    public function submit(User $user, Company $company, Project $project): void
    {
        $this->assertOwner($user, $company, $project);
        DB::transaction(function () use ($user, $project): void {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            abort_unless($locked->status === 'draft', 409);
            $locked->update(['status' => 'pending', 'published_at' => null]);
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
