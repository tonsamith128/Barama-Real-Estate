<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Inquiry;
use App\Models\AccountNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class InquiryCapture
{
    public function submit(string $slug, array $data, ?User $user): Inquiry
    {
        // A published listing is checked again at the moment of database write.
        return DB::transaction(function () use ($slug, $data, $user): Inquiry {
            $listing = app(PublishedListingSearch::class)->findPublicBySlug($slug);
            $inquiry = Inquiry::query()->create([
                'listing_id' => $listing->getKey(),
                'requester_id' => $user?->getKey(),
                'full_name' => trim($data['full_name']),
                'email' => mb_strtolower(trim($data['email'])),
                'phone' => isset($data['phone']) ? trim($data['phone']) : null,
                'message' => trim($data['message']),
                'status' => 'new',
            ]);
            if ($user !== null) {
                AccountNotification::query()->create([
                    'user_id' => $user->getKey(),
                    'title' => 'Property inquiry submitted',
                    'message' => 'Your inquiry about '.$listing->property->title.' was received.',
                    'type' => 'inquiry',
                ]);
            }
            return $inquiry;
        });
    }
}
