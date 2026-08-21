<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LinkType;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasFlexibleLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use BelongsToTenant;
    use HasFlexibleLink;

    protected $fillable = ['menu_id', 'parent_id', 'label', 'link_type', 'link_value', 'icon', 'badge_text', 'visibility', 'sort_order'];

    protected function casts(): array
    {
        return [
            'link_type' => LinkType::class,
            'visibility' => Visibility::class,
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
