<?php

declare(strict_types=1);

namespace App\Services\Inquiries;

use App\Models\AccountNotification;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InquiryConversationService
{
    public function assertCustomer(User $actor, Inquiry $inquiry): void
    {
        abort_unless($inquiry->requester_id === $actor->getKey(), 404);
    }

    public function assertDeveloper(User $actor, Inquiry $inquiry): void
    {
        $visible = Inquiry::query()->whereKey($inquiry->getKey())
            ->whereHas('listing.property.company', function (Builder $company) use ($actor): void {
                $company->where('status', 'approved')
                    ->whereHas('memberships', function (Builder $membership) use ($actor): void {
                        $membership->where('user_id', $actor->getKey())
                            ->where('status', 'active')
                            ->whereIn('role', ['owner', 'manager']);
                    });
            })->exists();

        abort_unless($visible, 404);
    }

    public function assertAdministrator(User $actor): void
    {
        abort_unless($actor->isSystemAdministrator(), 403);
    }

    public function messages(Inquiry $inquiry): LengthAwarePaginator
    {
        return $inquiry->messages()->with('sender:id,name')
            ->orderBy('id')->paginate(20);
    }

    public function send(User $actor, Inquiry $inquiry, string $body, string $workspace): InquiryMessage
    {
        $body = trim($body);
        if (mb_strlen($body) < 2 || mb_strlen($body) > 3000) {
            throw ValidationException::withMessages([
                'body' => 'Write a message between 2 and 3000 characters.',
            ]);
        }

        return DB::transaction(function () use ($actor, $inquiry, $body, $workspace): InquiryMessage {
            $locked = Inquiry::query()->lockForUpdate()
                ->with('listing.property.company')->findOrFail($inquiry->getKey());

            match ($workspace) {
                'account' => $this->assertCustomer($actor, $locked),
                'developer' => $this->assertDeveloper($actor, $locked),
                'admin' => $this->assertAdministrator($actor),
                default => abort(403),
            };

            if ($locked->requester_id === null) {
                throw ValidationException::withMessages([
                    'body' => 'This inquiry has no linked customer account for private messages.',
                ]);
            }

            $message = $locked->messages()->create([
                'sender_id' => $actor->getKey(),
                'body' => $body,
            ]);

            if ($workspace === 'account') {
                $locked->update(['status' => 'new', 'responded_at' => null]);
                $recipients = $locked->listing?->property?->company?->memberships()
                    ->where('status', 'active')->whereIn('role', ['owner', 'manager'])
                    ->where('user_id', '!=', $actor->getKey())
                    ->distinct()->pluck('user_id') ?? collect();
                $title = 'Customer replied to a property inquiry';
            } else {
                $locked->update(['status' => 'responded', 'responded_at' => now()]);
                $recipients = collect([$locked->requester_id])
                    ->filter(fn ($id) => $id !== $actor->getKey());
                $title = 'New reply to your property inquiry';
            }

            $subject = Str::limit((string) ($locked->listing?->property?->title ?? 'a property'), 110);
            foreach ($recipients->unique() as $recipient) {
                AccountNotification::query()->create([
                    'user_id' => $recipient,
                    'title' => $title,
                    'message' => 'A new message about '.$subject.' is available in the inquiries workspace.',
                    'type' => 'inquiry',
                ]);
            }

            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(),
                'action' => 'inquiry.message.sent',
                'subject_type' => 'inquiry',
                'subject_id' => $locked->getKey(),
                'context' => json_encode([
                    'workspace' => $workspace,
                    'message_id' => $message->getKey(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $message;
        });
    }
}
