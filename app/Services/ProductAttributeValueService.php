<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttributeDataType;
use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class ProductAttributeValueService
{
    public function set(Product|ProductVariant $owner, AttributeDefinition $attribute, mixed $value): void
    {
        $payload = match ($attribute->data_type) {
            AttributeDataType::Text => ['value_string' => (string) $value],
            AttributeDataType::Number => ['value_integer' => (int) $value],
            AttributeDataType::Decimal => ['value_decimal' => (float) $value],
            AttributeDataType::Boolean => ['value_boolean' => (bool) $value],
            AttributeDataType::Select => $this->resolveOption($attribute, $value),
            AttributeDataType::MultiSelect => throw ValidationException::withMessages([
                'value' => 'Multi-select values must be set individually per option.',
            ]),
        };

        $owner->attributeValues()->updateOrCreate(
            ['attribute_definition_id' => $attribute->id],
            $payload,
        );
    }

    private function resolveOption(AttributeDefinition $attribute, mixed $value): array
    {
        $option = $attribute->options()->where('id', $value)->firstOrFail();

        return ['attribute_option_id' => $option->id];
    }
}