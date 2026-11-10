<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Media;

use App\Models\Company;
use App\Models\CompanyMediaRevision;
use App\Models\MediaAsset;
use App\Models\UserCapabilityGrant;
use App\Services\Media\CompanyCapabilityGrantManager;
use App\Services\Media\CompanyMediaCapabilities;
use App\Services\Media\CompanyMediaRevisionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CompanyMediaController
{
    private function assertAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator()
            && $request->user()->hasVerifiedEmail(), 403);
    }

    public function index(Request $request, CompanyMediaCapabilities $permissions): View
    {
        $this->assertAdmin($request);
        return view('admin.media.company', [
            'canRequest' => $permissions->canRequest($request->user()),
            'canReview' => $permissions->canReview($request->user()),
            'capabilityOptions' => CompanyMediaCapabilities::ALL,
            'grants' => UserCapabilityGrant::query()->with('user')
                ->orderBy('user_id')->orderBy('capability')
                ->paginate(10, ['*'], 'grants_page'),
            'companies' => Company::query()->where('status', 'approved')
                ->whereNotNull('published_at')->orderBy('name')->limit(100)->get(),
            'assets' => MediaAsset::query()->whereIn('usage_rights', ['owned', 'licensed'])
                ->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp'])
                ->where('path', 'like', 'private/media/%')
                ->latest()->limit(100)->get(),
            'revisions' => CompanyMediaRevision::query()
                ->with(['company', 'asset', 'requester'])
                ->latest()->paginate(10, ['*'], 'reviews_page'),
        ]);
    }

    public function grant(
        Request $request, CompanyCapabilityGrantManager $manager,
    ): RedirectResponse {
        $this->assertAdmin($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'capability' => ['required', Rule::in(CompanyMediaCapabilities::ALL)],
        ]);
        $manager->grant($request->user(), $data['email'], $data['capability']);
        return back()->with('notice', 'Capability grant saved.');
    }

    public function revoke(
        Request $request, UserCapabilityGrant $grant,
        CompanyCapabilityGrantManager $manager,
    ): RedirectResponse {
        $this->assertAdmin($request);
        $manager->revoke($request->user(), $grant);
        return back()->with('notice', 'Capability grant revoked.');
    }

    public function request(
        Request $request, CompanyMediaRevisionManager $manager,
    ): RedirectResponse {
        $this->assertAdmin($request);
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'media_asset_id' => ['required', 'integer', Rule::exists('media_assets', 'id')],
            'collection' => ['required', Rule::in(CompanyMediaRevisionManager::COLLECTIONS)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $manager->request(
            $request->user(),
            Company::query()->findOrFail($data['company_id']),
            MediaAsset::query()->findOrFail($data['media_asset_id']),
            $data['collection'],
            $data['reason'],
        );
        return back()->with('notice', 'Media correction submitted for independent review. The published image is unchanged.');
    }

    public function decide(
        Request $request, CompanyMediaRevision $revision,
        CompanyMediaRevisionManager $manager,
    ): RedirectResponse {
        $this->assertAdmin($request);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['nullable', 'required_if:decision,rejected', 'string', 'min:10', 'max:2000'],
        ]);
        $manager->decide(
            $request->user(), $revision,
            $data['decision'], $data['reason'] ?? null,
        );
        return back()->with('notice', 'Media correction review completed.');
    }
}
