<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AccountNotification extends Model
{
    protected $fillable = ['user_id', 'title', 'message', 'type', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
