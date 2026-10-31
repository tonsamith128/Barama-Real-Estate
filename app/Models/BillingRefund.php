<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BillingRefund extends Model
{
    protected $fillable = [
        'reference', 'billing_payment_id', 'requested_by_user_id',
        'gateway_refund_id', 'amount_cents', 'reason', 'status', 'succeeded_at',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'succeeded_at' => 'datetime'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(BillingPayment::class, 'billing_payment_id'); }
}
