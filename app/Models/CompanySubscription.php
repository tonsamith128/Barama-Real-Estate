<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanySubscription extends Model
{
    protected $fillable = [
        'company_id', 'commercial_plan_id', 'billing_order_id',
        'status', 'billing_period', 'listing_limit', 'starts_at', 'ends_at',
        'gateway_subscription_id', 'gateway_customer_id',
    ];

    protected function casts(): array
    {
        return ['listing_limit' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function order(): BelongsTo { return $this->belongsTo(BillingOrder::class, 'billing_order_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(CommercialPlan::class, 'commercial_plan_id'); }

    public function isCurrent(): bool
    {
        return $this->status === 'active'
            && $this->starts_at !== null && $this->starts_at->lte(now())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
