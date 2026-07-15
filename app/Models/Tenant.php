<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
    'name', 'subdomain', 'status', 'plan',
     'currency',  'contact_email', 'contact_phone',
];

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

        public function themeSettings(): HasOne
    {
        return $this->hasOne(StoreThemeSetting::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(StoreSetting::class);
    }
}