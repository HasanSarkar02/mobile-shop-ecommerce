<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'product_id', 'product_variant_id', 'attribute_definition_id', 'attribute_option_id',
        'value_string', 'value_integer', 'value_decimal', 'value_boolean',
    ];

    protected function casts(): array
    {
        return [
            'value_integer' => 'integer',
            'value_decimal' => 'decimal:2',
            'value_boolean' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class);
    }

    public function attributeOption(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class);
    }

    public function displayValue(): ?string
    {
        return match (true) {
            $this->attribute_option_id !== null => $this->attributeOption?->label,
            $this->value_boolean !== null => $this->value_boolean ? 'Yes' : 'No',
            $this->value_decimal !== null => (string) $this->value_decimal,
            $this->value_integer !== null => (string) $this->value_integer,
            default => $this->value_string,
        };
    }
}