<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'subdomain', 'status', 'plan', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}