<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DemoImport extends Model
{
    protected $fillable = [
        'archive_sha256', 'archive_version', 'imported_by',
        'marketplace_records', 'image_count', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
