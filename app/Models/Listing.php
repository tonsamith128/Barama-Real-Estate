<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\RealEstate\MarketplaceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Listing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_id', 'slug', 'transaction_type', 'status', 'price',
        'currency', 'pricing_period', 'negotiable', 'published_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'negotiable' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function mediaAttachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class, 'entity_id')
            ->where('entity_type', 'listing')->orderBy('position');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * A public offer requires an approved property AND an approved published
     * company. Laravel SoftDeletes removes archived records automatically.
     */
    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query
            ->where('status', MarketplaceStatus::LISTING_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereHas('property', function (Builder $property): void {
                $property->approved()
                    ->whereHas('company', fn (Builder $company) => $company->publiclyVisible());
            });
    }
}
