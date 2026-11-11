<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LegacyCustomerLead extends Model
{
    protected $fillable = ['review_status','reviewed_by','reviewed_at','reviewer_note'];

    protected function casts(): array
    {
        return [
            'budget_min'=>'decimal:2','budget_max'=>'decimal:2',
            'last_contact_at'=>'datetime','next_follow_up_at'=>'datetime',
            'won_at'=>'datetime','lost_at'=>'datetime',
            'deleted_at'=>'datetime','reviewed_at'=>'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(LegacyCustomerInquiry::class,'inquiry_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class,'reviewed_by');
    }
}
