<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LegacyCustomerInquiry extends Model
{
    protected $fillable = ['review_status','reviewed_by','reviewed_at','reviewer_note'];

    protected function casts(): array
    {
        return [
            'responded_at'=>'datetime','closed_at'=>'datetime','reviewed_at'=>'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class,'reviewed_by');
    }
}
