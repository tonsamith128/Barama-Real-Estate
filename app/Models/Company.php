<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\RealEstate\MarketplaceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'company_type', 'status', 'description', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', MarketplaceStatus::COMPANY_APPROVED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function billingProfile(): HasOne
    {
        return $this->hasOne(CompanyBillingProfile::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
