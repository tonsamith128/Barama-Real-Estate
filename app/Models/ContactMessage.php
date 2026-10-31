<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ContactMessage extends Model
{
    protected $fillable = ['user_id', 'full_name', 'email', 'subject', 'message', 'status'];
}
