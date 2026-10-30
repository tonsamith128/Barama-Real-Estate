<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MediaAttachment extends Model
{
    protected $fillable = [
        'media_asset_id', 'entity_type', 'entity_id', 'collection',
        'position', 'is_primary',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
