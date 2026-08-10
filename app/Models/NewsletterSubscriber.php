<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use BelongsToTenant;

    protected $fillable = ['email', 'subscribed_at'];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
        ];
    }
}