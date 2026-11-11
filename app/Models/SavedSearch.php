<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SavedSearch extends Model
{
    protected $fillable = ['user_id', 'name', 'filters'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'legacy_filter_evidence' => 'array',
            'legacy_review_required' => 'boolean',
            'legacy_alerts_enabled' => 'boolean',
            'legacy_last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
