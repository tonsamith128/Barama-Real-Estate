<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\RealEstate\MarketplaceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'reference_code', 'title', 'property_type',
        'status', 'description', 'address_line', 'bedrooms', 'bathrooms',
        'area_value', 'area_unit', 'location_id', 'parking_spaces',
        'floor_number', 'total_floors', 'land_area_value', 'land_area_unit',
        'furnishing_status', 'year_built', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'bedrooms' => 'decimal:1',
            'bathrooms' => 'decimal:1',
            'area_value' => 'decimal:2',
            'parking_spaces' => 'integer',
            'floor_number' => 'integer',
            'total_floors' => 'integer',
            'land_area_value' => 'decimal:2',
            'year_built' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenities');
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
