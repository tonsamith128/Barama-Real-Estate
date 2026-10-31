<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Legal;

use App\Models\LegalPolicy;
use App\Services\Policy\LegalHtmlSanitizer;
use Illuminate\View\View;

final class LegalPageController
{
    public function index(): View
    {
        return view('frontend.legal.index', [
            'policies' => LegalPolicy::query()->where('status','published')
                ->orderBy('title')->get(),
        ]);
    }

    public function show(string $slug, LegalHtmlSanitizer $sanitizer): View
    {
        $policy = LegalPolicy::query()->where('status','published')
            ->where('slug', $slug)->firstOrFail();
        return view('frontend.legal.show', [
            'policy' => $policy,
            'safeHtml' => $sanitizer->clean((string) $policy->content_html),
        ]);
    }
}
