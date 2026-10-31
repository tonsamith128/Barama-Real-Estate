<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\Notifications;

use App\Models\AccountNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NotificationController
{
    public function index(Request $request): View
    {
        return view('account.notifications.index', [
            'notifications' => AccountNotification::query()->where('user_id', $request->user()->getKey())
                ->latest()->paginate(20),
        ]);
    }

    public function read(Request $request, AccountNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->getKey(), 404);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return back()->with('notice', 'Notification marked as read.');
    }
}
