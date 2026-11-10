<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Projects;

use App\Models\Company;
use App\Models\Project;
use App\Models\Location;
use App\Models\Amenity;
use App\Services\Developer\ProjectManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DeveloperProjectController
{
    public function index(Request $request, Company $company, ProjectManager $manager): View
    {
        $manager->assertOwnerAccess($request->user(), $company);
        return view('developer.projects.index', [
            'company' => $company,
            'projects' => $company->projects()->with(['mediaAttachments.asset', 'amenities', 'milestones'])->latest()->paginate(20),
            'amenities' => Amenity::query()->active()->orderBy('name')->get(),
            'locations' => Location::query()->where('status', 'active')->orderBy('country_code')->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function store(Request $request, Company $company, ProjectManager $manager): RedirectResponse
    {
        $data = $this->validated($request);
        $manager->create($request->user(), $company, $data);
        return redirect()->route('developer.projects.index', $company)
            ->with('notice', 'Project draft created. Submit when ready for review.');
    }

    public function update(Request $request, Company $company, Project $project, ProjectManager $manager): RedirectResponse
    {
        $data = $this->validated($request);
        $manager->revise($request->user(), $company, $project, $data);
        return redirect()->route('developer.projects.index', $company)
            ->with('notice', 'Project draft updated.');
    }

    public function submit(Request $request, Company $company, Project $project, ProjectManager $manager): RedirectResponse
    {
        $manager->submit($request->user(), $company, $project);
        return redirect()->route('developer.projects.index', $company)
            ->with('notice', 'Project submitted for administrator review.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:4', 'max:190'],
            'project_type' => ['required', Rule::in(['residential', 'commercial', 'mixed_use', 'industrial', 'other'])],
            'completion_status' => ['nullable', Rule::in(['planning', 'under_construction', 'completed'])],
            'description' => ['nullable', 'string', 'max:12000'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('status', 'active')],
            'address_line' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'launch_date' => ['nullable', 'date_format:Y-m-d'],
            'expected_completion_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:launch_date'],
            'completed_at' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }
}
