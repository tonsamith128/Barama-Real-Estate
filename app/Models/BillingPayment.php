<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BillingPayment extends Model
{
    protected $fillable = [
        'billing_order_id', 'gateway', 'gateway_payment_id', 'amount_cents',
        'refunded_cents', 'currency', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'refunded_cents' => 'integer', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(BillingOrder::class, 'billing_order_id'); }
    public function refunds(): HasMany { return $this->hasMany(BillingRefund::class); }
}
