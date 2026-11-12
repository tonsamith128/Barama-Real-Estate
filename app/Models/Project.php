<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'slug', 'project_type', 'status',
        'completion_status', 'description', 'published_at',
        'location_id', 'address_line', 'latitude', 'longitude',
        'launch_date', 'expected_completion_date', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'launch_date' => 'date',
            'expected_completion_date' => 'date',
            'completed_at' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('company', fn (Builder $company) => $company->publiclyVisible());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function mediaAttachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class, 'entity_id')
            ->where('entity_type', 'project')
            ->where('collection', 'gallery')
            ->orderBy('position')->orderBy('id');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'project_amenities');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)
            ->orderBy('position')->orderBy('id');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
