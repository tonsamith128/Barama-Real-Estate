<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ViewingRequest extends Model
{
    protected $fillable = ['user_id', 'listing_id', 'requested_at', 'message', 'status'];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'legacy_preferred_date' => 'date',
            'legacy_alternate_date' => 'date',
            'legacy_scheduled_for' => 'datetime',
            'legacy_cancelled_at' => 'datetime',
            'legacy_completed_at' => 'datetime',
            'proposed_at' => 'datetime',
            'proposed_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ViewingEvent::class)->oldest();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
