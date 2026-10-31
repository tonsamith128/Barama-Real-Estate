<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BillingInvoice extends Model
{
    protected $fillable = [
        'billing_order_id', 'invoice_number', 'status', 'total_cents',
        'refunded_cents', 'currency', 'paid_at',
        'customer_billing_name', 'customer_country_code',
    ];

    protected function casts(): array
    {
        return ['total_cents' => 'integer', 'refunded_cents' => 'integer', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(BillingOrder::class, 'billing_order_id'); }
}
