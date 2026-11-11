<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ViewingEvent extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'viewing_request_id', 'actor_id', 'actor_kind', 'event_type',
        'from_status', 'to_status', 'previous_time', 'new_time', 'reason',
    ];

    protected function casts(): array
    {
        return ['previous_time' => 'datetime', 'new_time' => 'datetime'];
    }

    public function viewing(): BelongsTo
    {
        return $this->belongsTo(ViewingRequest::class, 'viewing_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
