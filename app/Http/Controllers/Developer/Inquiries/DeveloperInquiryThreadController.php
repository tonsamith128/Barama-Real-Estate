<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Inquiries;

use App\Models\Inquiry;
use App\Services\Inquiries\InquiryConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DeveloperInquiryThreadController
{
    public function show(Request $request, Inquiry $inquiry, InquiryConversationService $conversation): View
    {
        $conversation->assertDeveloper($request->user(), $inquiry);
        $inquiry->load('listing.property.company');

        return view('developer.inquiries.thread', [
            'inquiry' => $inquiry,
            'messages' => $conversation->messages($inquiry),
            'replyAction' => route('developer.inquiries.messages.store', $inquiry),
        ]);
    }

    public function store(Request $request, Inquiry $inquiry, InquiryConversationService $conversation): RedirectResponse
    {
        $conversation->assertDeveloper($request->user(), $inquiry);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);
        $conversation->send($request->user(), $inquiry, $data['body'], 'developer');

        return redirect()->route('developer.inquiries.show', $inquiry)
            ->with('notice', 'Your message was saved and the relevant account notified.');
    }
}
