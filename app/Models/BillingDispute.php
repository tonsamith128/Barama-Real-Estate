<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BillingDispute extends Model
{
    protected $fillable = [
        'gateway_dispute_id', 'billing_payment_id', 'gateway_charge_id',
        'gateway_payment_id', 'amount_cents', 'currency', 'status',
        'needs_reconciliation', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'needs_reconciliation' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(BillingPayment::class, 'billing_payment_id');
    }
}
