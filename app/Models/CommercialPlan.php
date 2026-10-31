<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CommercialPlan extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'price_cents', 'currency',
        'billing_period', 'listing_limit', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'listing_limit' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(CompanyPlanInquiry::class);
    }

    public function formattedPrice(): string
    {
        return '$'.number_format($this->price_cents / 100, 2, '.', ',');
    }
}
