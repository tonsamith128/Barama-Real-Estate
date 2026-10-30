<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MediaAsset extends Model
{
    protected $fillable = [
        'path', 'original_name', 'mime_type', 'size_bytes',
        'width_px', 'height_px', 'alt_text', 'checksum_sha256',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }
}
