<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LegalPolicy extends Model
{
    protected $fillable = ['slug', 'title', 'summary', 'content_html', 'status'];
}
