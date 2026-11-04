<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Inquiries;

use App\Models\Inquiry;
use App\Services\Inquiries\InquiryConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminInquiryThreadController
{
    public function show(Request $request, Inquiry $inquiry, InquiryConversationService $conversation): View
    {
        $conversation->assertAdministrator($request->user());
        $inquiry->load('listing.property.company');

        return view('admin.inquiries.thread', [
            'inquiry' => $inquiry,
            'messages' => $conversation->messages($inquiry),
            'replyAction' => route('admin.inquiries.messages.store', $inquiry),
        ]);
    }

    public function store(Request $request, Inquiry $inquiry, InquiryConversationService $conversation): RedirectResponse
    {
        $conversation->assertAdministrator($request->user());
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ]);
        $conversation->send($request->user(), $inquiry, $data['body'], 'admin');

        return redirect()->route('admin.inquiries.show', $inquiry)
            ->with('notice', 'Your message was saved in the private inquiry conversation.');
    }
}
