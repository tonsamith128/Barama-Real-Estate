<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class BillingOrder extends Model
{
    protected $fillable = [
        'company_id', 'commercial_plan_id', 'requested_by_user_id',
        'reference', 'gateway', 'status', 'plan_name', 'amount_cents',
        'currency', 'billing_period', 'listing_limit', 'gateway_session_id',
        'gateway_payment_id', 'paid_at', 'checkout_mode', 'gateway_subscription_id',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'listing_limit' => 'integer', 'paid_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function plan(): BelongsTo { return $this->belongsTo(CommercialPlan::class, 'commercial_plan_id'); }
    public function invoice(): HasOne { return $this->hasOne(BillingInvoice::class); }
    public function payment(): HasOne { return $this->hasOne(BillingPayment::class); }
}
