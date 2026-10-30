<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\RealEstate\MarketplaceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'reference_code', 'title', 'property_type',
        'status', 'description', 'address_line', 'bedrooms', 'bathrooms',
        'area_value', 'area_unit',
    ];

    protected function casts(): array
    {
        return [
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'area_value' => 'decimal:2',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', MarketplaceStatus::PROPERTY_APPROVED);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
