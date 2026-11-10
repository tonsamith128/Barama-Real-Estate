<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

final class Amenity extends Model
{
    protected $fillable = [
        'uuid', 'name', 'slug', 'category', 'icon_key', 'status', 'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $amenity): void {
            if (!$amenity->uuid) {
                $amenity->uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_amenities');
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_amenities');
    }
}
