<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BillingRenewal extends Model
{
    protected $fillable = [
        'billing_order_id', 'gateway_invoice_id',
        'gateway_subscription_id', 'period_start', 'period_end',
    ];

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BillingOrder::class, 'billing_order_id');
    }
}
