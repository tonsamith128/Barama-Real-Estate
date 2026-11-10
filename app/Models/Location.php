<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class Location extends Model
{
    public const TYPES = [
        'country', 'province', 'state', 'city', 'district', 'commune', 'neighborhood',
    ];

    protected $fillable = [
        'uuid', 'name', 'slug', 'type', 'country_code', 'parent_id',
        'status', 'sort_order', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $location): void {
            if (! $location->uuid) {
                $location->uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('locations.status', 'active');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
