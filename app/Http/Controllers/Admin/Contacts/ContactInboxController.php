<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Contacts;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ContactInboxController
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        return view('admin.contacts.index', [
            'messages' => ContactMessage::query()->latest()->paginate(20),
        ]);
    }

    public function read(Request $request, ContactMessage $message): RedirectResponse
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        $message->update(['status' => 'reviewed']);
        return back()->with('notice', 'Message marked as reviewed.');
    }
}
