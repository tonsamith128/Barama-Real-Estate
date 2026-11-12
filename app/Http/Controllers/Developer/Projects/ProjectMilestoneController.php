<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Projects;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\Developer\ProjectMilestoneManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ProjectMilestoneController
{
    /** @return array<string, mixed> */
    private function fields(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:190'],
            'description' => ['nullable', 'string', 'max:6000'],
            'status' => ['required', Rule::in(['planned', 'in_progress', 'completed', 'delayed'])],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'completed_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }

    public function store(
        Request $request, Company $company, Project $project,
        ProjectMilestoneManager $milestones,
    ): RedirectResponse {
        $milestones->create($request->user(), $company, $project, $this->fields($request));
        return back()->with('notice', 'Project milestone created.');
    }

    public function update(
        Request $request, Company $company, Project $project,
        ProjectMilestone $milestone, ProjectMilestoneManager $milestones,
    ): RedirectResponse {
        $milestones->update($request->user(), $company, $project, $milestone, $this->fields($request));
        return back()->with('notice', 'Project milestone updated.');
    }

    public function destroy(
        Request $request, Company $company, Project $project,
        ProjectMilestone $milestone, ProjectMilestoneManager $milestones,
    ): RedirectResponse {
        $milestones->destroy($request->user(), $company, $project, $milestone);
        return back()->with('notice', 'Project milestone removed; revision history retained.');
    }

    public function move(
        Request $request, Company $company, Project $project,
        ProjectMilestone $milestone, ProjectMilestoneManager $milestones,
    ): RedirectResponse {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $milestones->move($request->user(), $company, $project, $milestone, $data['direction']);
        return back()->with('notice', 'Project milestones reordered.');
    }
}
