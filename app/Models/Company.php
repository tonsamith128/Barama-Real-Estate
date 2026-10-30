<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
