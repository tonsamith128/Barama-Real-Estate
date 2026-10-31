<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SiteAppearance extends Model
{
    protected $fillable = ['draft_data', 'published_data', 'published_at'];

    protected function casts(): array
    {
        return ['draft_data' => 'array', 'published_data' => 'array', 'published_at' => 'datetime'];
    }
}
