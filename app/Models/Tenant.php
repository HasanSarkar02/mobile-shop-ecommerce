<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable = [
    'name', 'subdomain', 'status', 'plan',
     'currency',  'contact_email', 'contact_phone',
];


    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

        public function themeSettings(): HasOne
    {
        return $this->hasOne(StoreThemeSetting::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(StoreSetting::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active'], true);
    }
}