<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appearance;

use App\Services\Appearance\SiteAppearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AppearanceController
{
    private function authorize(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request, SiteAppearanceService $appearance): View
    {
        $this->authorize($request);
        return view('admin.appearance.index', [
            'draft' => $appearance->editing(),
            'live' => $appearance->published(),
        ]);
    }

    public function save(Request $request, SiteAppearanceService $appearance): RedirectResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'min:3', 'max:190'],
            'brand_short' => ['required', 'string', 'min:2', 'max:60'],
            'brand_subtitle' => ['required', 'string', 'max:60'],
            'primary_color' => ['required', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'hero_eyebrow' => ['required', 'string', 'max:120'],
            'hero_title' => ['required', 'string', 'min:10', 'max:190'],
            'hero_description' => ['required', 'string', 'max:500'],
            'footer_text' => ['required', 'string', 'max:300'],
            'meta_description' => ['required', 'string', 'max:300'],
        ]);
        $appearance->saveDraft($request->user(), $data);
        return back()->with('notice', 'Appearance draft saved. Public website remains unchanged until publication.');
    }

    public function publish(Request $request, SiteAppearanceService $appearance): RedirectResponse
    {
        $this->authorize($request);
        $appearance->publish($request->user());
        return back()->with('notice', 'Appearance settings published to the website.');
    }

    public function resetDraft(Request $request, SiteAppearanceService $appearance): RedirectResponse
    {
        $this->authorize($request);
        $appearance->resetDraft($request->user());
        return back()->with('notice', 'Draft reverted to the currently published settings.');
    }
}
