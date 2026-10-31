<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyBillingProfile extends Model
{
    protected $fillable = [
        'company_id', 'billing_name', 'billing_email', 'country_code',
        'address_line', 'city', 'region', 'postal_code',
        'preferred_locale', 'preferred_currency', 'timezone',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
