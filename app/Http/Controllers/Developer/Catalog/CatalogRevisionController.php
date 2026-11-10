<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Catalog;

use App\Models\Company;
use App\Models\Project;
use App\Models\Property;
use App\Services\Developer\CompanyCatalogWriter;
use App\Services\Developer\ProjectManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CatalogRevisionController
{
    public function project(
        Request $request, Company $company, Project $project,
        ProjectManager $projects,
    ): View {
        $projects->assertOwner($request->user(), $company, $project);
        $revisions = DB::table('catalog_revisions')
            ->where('subject_type', 'project')
            ->where('subject_id', $project->getKey())
            ->orderByDesc('id')->paginate(10);

        return $this->viewHistory($company, 'Project', $project->name,
            route('developer.projects.index', $company), $revisions);
    }

    public function property(
        Request $request, Company $company, Property $property,
        CompanyCatalogWriter $properties,
    ): View {
        $properties->assertCanManage($request->user(), $company);
        abort_unless($property->company_id === $company->getKey(), 404);

        $listingIds = $property->listings()->pluck('id')->all();
        $revisions = DB::table('catalog_revisions')->where(function ($query) use ($property, $listingIds): void {
            $query->where(function ($child) use ($property): void {
                $child->where('subject_type', 'property')
                    ->where('subject_id', $property->getKey());
            });
            if ($listingIds !== []) {
                $query->orWhere(function ($child) use ($listingIds): void {
                    $child->where('subject_type', 'listing')
                        ->whereIn('subject_id', $listingIds);
                });
            }
        })->orderByDesc('id')->paginate(10);

        return $this->viewHistory($company, 'Property', $property->title,
            route('developer.properties.index', $company), $revisions);
    }

    private function viewHistory(
        Company $company, string $type, string $title, string $backUrl,
        \Illuminate\Contracts\Pagination\LengthAwarePaginator $revisions,
    ): View {
        $revisions->getCollection()->transform(function ($revision) {
            $before = json_decode((string) ($revision->before_snapshot ?? ''), true);
            $after = json_decode((string) ($revision->after_snapshot ?? ''), true);
            $before = is_array($before) ? $before : [];
            $after = is_array($after) ? $after : [];
            $changes = [];

            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
                $old = $before[$field] ?? null;
                $new = $after[$field] ?? null;
                if ($old !== $new) {
                    $changes[] = [
                        'field' => $field,
                        'before' => $this->display($old),
                        'after' => $this->display($new),
                    ];
                }
            }
            $revision->changes = $changes;
            return $revision;
        });

        return view('developer.catalog.history', [
            'company' => $company,
            'subjectType' => $type,
            'subjectName' => $title,
            'backUrl' => $backUrl,
            'revisions' => $revisions,
        ]);
    }

    private function display(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string) $value;
    }
}
