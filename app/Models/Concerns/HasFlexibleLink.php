<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\LinkType;

trait HasFlexibleLink
{
    public function resolveUrl(): ?string
    {
        $type = $this->link_type instanceof LinkType ? $this->link_type->value : $this->link_type;

        if (! $this->link_value && $type !== LinkType::None->value) {
            return null;
        }

        return match ($type) {
            'product' => '/product/'.$this->link_value,
            'category' => '/category/'.$this->link_value,
            'brand' => '/brand/'.$this->link_value,
            'collection' => '/collection/'.$this->link_value,
            'static_page' => '/page/'.$this->link_value,
            'external' => $this->link_value,
            default => null,
        };
    }
}
