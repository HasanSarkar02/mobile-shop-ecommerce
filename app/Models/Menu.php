<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuLocation;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'location'];

    protected function casts(): array
    {
        return ['location' => MenuLocation::class];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function topLevelItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }
}