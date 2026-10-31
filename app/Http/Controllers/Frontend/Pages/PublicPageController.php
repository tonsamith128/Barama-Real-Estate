<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Pages;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicPageController
{
    public function about(): View
    {
        return view('frontend.pages.about');
    }

    public function faq(): View
    {
        return view('frontend.pages.faq');
    }

    public function contact(): View
    {
        return view('frontend.pages.contact');
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'min:4', 'max:190'],
            'message' => ['required', 'string', 'min:12', 'max:4000'],
            'consent' => ['required', 'accepted'],
            'website' => ['prohibited'],
        ]);
        ContactMessage::query()->create([
            'user_id' => $request->user()?->getKey(),
            'full_name' => $input['full_name'],
            'email' => $input['email'],
            'subject' => $input['subject'],
            'message' => $input['message'],
        ]);
        return redirect()->route('contact')
            ->with('notice', 'Your message was received. We will review it.');
    }
}
