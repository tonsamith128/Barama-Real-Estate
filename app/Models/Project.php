<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'slug', 'project_type', 'status',
        'completion_status', 'description', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
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

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
