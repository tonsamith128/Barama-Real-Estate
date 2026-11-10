<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class LocationCountry extends Model
{
    protected $primaryKey = 'country_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'country_code', 'iso3', 'name', 'native_name',
        'enabled', 'is_default', 'sort_order', 'data_status',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
