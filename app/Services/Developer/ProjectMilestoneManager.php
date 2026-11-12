<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProjectMilestoneManager
{
    public function __construct(
        private readonly ProjectManager $projects,
        private readonly CatalogRevisionRecorder $revisions,
    ) {
    }

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, Company $company, Project $project, array $attributes): ProjectMilestone
    {
        $this->projects->assertOwner($actor, $company, $project);
        $this->validateCompletion($attributes);

        return DB::transaction(function () use ($actor, $company, $project, $attributes): ProjectMilestone {
            $locked = $this->draft($actor, $company, $project);
            $before = $this->snapshotAll($locked);
            abort_if(count($before) >= 100, 422, 'A project supports up to 100 milestones.');
            $milestone = $locked->milestones()->create([
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'],
                'target_date' => $attributes['target_date'] ?? null,
                'completed_date' => $attributes['completed_date'] ?? null,
                'position' => count($before),
            ]);
            $this->record($actor, $company, $locked, $before, $this->snapshotAll($locked));
            return $milestone;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        User $actor, Company $company, Project $project, ProjectMilestone $milestone, array $attributes,
    ): void {
        $this->projects->assertOwner($actor, $company, $project);
        $this->validateCompletion($attributes);

        DB::transaction(function () use ($actor, $company, $project, $milestone, $attributes): void {
            $locked = $this->draft($actor, $company, $project);
            $before = $this->snapshotAll($locked);
            $item = $locked->milestones()->lockForUpdate()->findOrFail($milestone->getKey());
            $item->update([
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'],
                'target_date' => $attributes['target_date'] ?? null,
                'completed_date' => $attributes['completed_date'] ?? null,
            ]);
            $this->record($actor, $company, $locked, $before, $this->snapshotAll($locked));
        });
    }

    public function destroy(User $actor, Company $company, Project $project, ProjectMilestone $milestone): void
    {
        $this->projects->assertOwner($actor, $company, $project);
        DB::transaction(function () use ($actor, $company, $project, $milestone): void {
            $locked = $this->draft($actor, $company, $project);
            $before = $this->snapshotAll($locked);
            $locked->milestones()->lockForUpdate()->findOrFail($milestone->getKey())->delete();

            foreach ($locked->milestones()->get() as $position => $item) {
                if ($item->position !== $position) {
                    $item->update(['position' => $position]);
                }
            }
            $this->record($actor, $company, $locked, $before, $this->snapshotAll($locked));
        });
    }

    public function move(
        User $actor, Company $company, Project $project,
        ProjectMilestone $milestone, string $direction,
    ): void {
        abort_unless(in_array($direction, ['up', 'down'], true), 422);
        $this->projects->assertOwner($actor, $company, $project);

        DB::transaction(function () use ($actor, $company, $project, $milestone, $direction): void {
            $locked = $this->draft($actor, $company, $project);
            $items = $locked->milestones()->lockForUpdate()->get();
            $before = $this->snapshotAll($locked);
            $ids = $items->modelKeys();
            $index = array_search($milestone->getKey(), $ids);
            abort_if($index === false, 404);
            $otherIndex = $index + ($direction === 'up' ? -1 : 1);
            if ($otherIndex < 0 || $otherIndex >= count($ids)) {
                return;
            }

            [$ids[$index], $ids[$otherIndex]] = [$ids[$otherIndex], $ids[$index]];
            foreach ($ids as $position => $id) {
                $item = $items->firstWhere('id', $id);
                $item->update(['position' => $position]);
            }
            $this->record($actor, $company, $locked, $before, $this->snapshotAll($locked));
        });
    }

    private function draft(User $actor, Company $company, Project $project): Project
    {
        $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
        $this->projects->assertOwner($actor, $company, $locked);
        abort_unless(in_array($locked->status, ['draft', 'rejected'], true), 409);
        return $locked;
    }

    /** @return array<int, array<string, mixed>> */
    private function snapshotAll(Project $project): array
    {
        return $project->milestones()->get()->map(static fn (ProjectMilestone $item): array => [
            'id' => $item->getKey(), 'title' => $item->title,
            'description' => $item->description, 'status' => $item->status,
            'position' => $item->position,
            'target_date' => $item->target_date?->format('Y-m-d'),
            'completed_date' => $item->completed_date?->format('Y-m-d'),
        ])->all();
    }

    /** @param array<string, mixed> $attributes */
    private function validateCompletion(array $attributes): void
    {
        if (($attributes['status'] ?? null) === 'completed'
            && empty($attributes['completed_date'])) {
            throw ValidationException::withMessages([
                'completed_date' => 'Completed milestones require an actual completion date.',
            ]);
        }
        if (($attributes['status'] ?? null) !== 'completed'
            && !empty($attributes['completed_date'])) {
            throw ValidationException::withMessages([
                'completed_date' => 'Only completed milestones can have a completion date.',
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $before
     * @param array<int, array<string, mixed>> $after
     */
    private function record(
        User $actor, Company $company, Project $project, array $before, array $after,
    ): void {
        if ($before === $after) {
            return;
        }
        $this->revisions->record($actor, 'project', $project, 'milestone_changed',
            ['milestones' => $before], ['milestones' => $after]);
        DB::table('audit_events')->insert([
            'actor_id' => $actor->getKey(),
            'action' => 'developer.project.milestone_changed',
            'subject_type' => 'project',
            'subject_id' => $project->getKey(),
            'context' => json_encode(['company_id' => $company->getKey()], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
