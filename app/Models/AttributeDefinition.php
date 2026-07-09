<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttributeDataType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeDefinition extends Model
{
    use BelongsToTenant;

    protected $fillable = ['code', 'label', 'data_type', 'unit', 'is_global', 'is_filterable', 'is_variant_defining', 'sort_order'];

    protected function casts(): array
    {
        return [
            'data_type' => AttributeDataType::class,
            'is_global' => 'boolean',
            'is_filterable' => 'boolean',
            'is_variant_defining' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attribute_definition');
    }

    public function isChoiceType(): bool
    {
        return in_array($this->data_type, [AttributeDataType::Select, AttributeDataType::MultiSelect], true);
    }
}