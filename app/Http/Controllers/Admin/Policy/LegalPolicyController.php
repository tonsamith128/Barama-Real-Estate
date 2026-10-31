<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Policy;

use App\Models\LegalPolicy;
use App\Services\Policy\LegalHtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class LegalPolicyController
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        return view('admin.policies.index', [
            'policies' => LegalPolicy::query()->orderBy('title')->paginate(25),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAdmin($request);
        return view('admin.policies.edit', ['policy' => new LegalPolicy()]);
    }

    public function edit(Request $request, LegalPolicy $policy): View
    {
        $this->authorizeAdmin($request);
        return view('admin.policies.edit', compact('policy'));
    }

    public function store(Request $request, LegalHtmlSanitizer $sanitizer): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validInput($request);
        $clean = $sanitizer->clean($data['content_html']);
        $this->checkContent($clean);
        $policy = DB::transaction(function () use ($request, $data, $clean): LegalPolicy {
            $policy = LegalPolicy::query()->create([
                'slug' => $data['slug'],
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'content_html' => $clean,
                'status' => 'draft',
            ]);
            $this->audit($request, $policy, 'admin.policy.created');
            return $policy;
        });
        return redirect()->route('admin.policies.edit', $policy)
            ->with('notice', 'Draft saved. Publish when the content is ready.');
    }

    public function update(
        Request $request, LegalPolicy $policy, LegalHtmlSanitizer $sanitizer
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        $data = $this->validInput($request, $policy);
        $clean = $sanitizer->clean($data['content_html']);
        $this->checkContent($clean);
        DB::transaction(function () use ($request, $policy, $data, $clean): void {
            $policy->update([
                'slug' => $data['slug'],
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'content_html' => $clean,
                'status' => 'draft', // edits always require an explicit republish
            ]);
            $this->audit($request, $policy, 'admin.policy.updated');
        });
        return back()->with('notice', 'Saved as draft. Choose Publish to make it public.');
    }

    public function publish(
        Request $request, LegalPolicy $policy, LegalHtmlSanitizer $sanitizer
    ): RedirectResponse {
        $this->authorizeAdmin($request);
        DB::transaction(function () use ($request, $policy, $sanitizer): void {
            $fresh = LegalPolicy::query()->lockForUpdate()->findOrFail($policy->getKey());
            $clean = $sanitizer->clean((string) $fresh->content_html);
            $this->checkContent($clean);
            $fresh->update(['content_html' => $clean, 'status' => 'published']);
            $this->audit($request, $fresh, 'admin.policy.published');
        });
        return redirect()->route('admin.policies.index')->with('notice', 'Legal page published.');
    }

    public function unpublish(Request $request, LegalPolicy $policy): RedirectResponse
    {
        $this->authorizeAdmin($request);
        DB::transaction(function () use ($request, $policy): void {
            $fresh = LegalPolicy::query()->lockForUpdate()->findOrFail($policy->getKey());
            $fresh->update(['status' => 'draft']);
            $this->audit($request, $fresh, 'admin.policy.unpublished');
        });
        return back()->with('notice', 'Legal page unpublished.');
    }

    private function validInput(Request $request, ?LegalPolicy $policy = null): array
    {
        return $request->validate([
            'slug' => ['required','string','max:190',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('legal_policies', 'slug')->ignore($policy?->getKey()),
            ],
            'title' => ['required','string','min:3','max:190'],
            'summary' => ['nullable','string','max:500'],
            'content_html' => ['required','string','min:10','max:100000'],
        ]);
    }

    private function checkContent(string $html): void
    {
        if (mb_strlen(trim(html_entity_decode(strip_tags($html)))) < 3) {
            throw ValidationException::withMessages([
                'content_html' => 'Add the legal page text before publishing.',
            ]);
        }
    }

    private function audit(Request $request, LegalPolicy $policy, string $action): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => $request->user()->getKey(),
            'action' => $action,
            'subject_type' => 'legal_policy',
            'subject_id' => $policy->getKey(),
            'context' => json_encode(['slug' => $policy->slug], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
