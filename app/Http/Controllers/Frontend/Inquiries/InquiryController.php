<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Inquiries;

use App\Services\Marketplace\InquiryCapture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InquiryController
{
    public function store(Request $request, string $slug, InquiryCapture $capture): RedirectResponse
    {
        $attributes = $request->validate([
            'full_name' => ['required','string','min:2','max:120'],
            'email' => ['required','email','max:190'],
            'phone' => ['nullable','string','max:50'],
            'message' => ['required','string','min:10','max:4000'],
            'consent' => ['required','accepted'],
            'website' => ['prohibited'],
        ]);
        $capture->submit($slug, $attributes, $request->user());
        return redirect()->route('properties.show', $slug)
            ->with('notice', 'Your inquiry was received by the marketplace.');
    }
}
