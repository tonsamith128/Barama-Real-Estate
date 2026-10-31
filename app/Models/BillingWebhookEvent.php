<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BillingWebhookEvent extends Model
{
    protected $fillable = [
        'gateway', 'gateway_event_id', 'event_type', 'status', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
