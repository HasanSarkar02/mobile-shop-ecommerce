<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierProvider extends Model
{
    protected $fillable = [
        'code', 'name', 'display_name', 'base_url', 'base_url_sandbox', 'base_url_live',
        'auth_type', 'required_fields', 'driver_class', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required_fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function displayName(): string
    {
        return $this->display_name ?: $this->name;
    }

    public function effectiveBaseUrl(bool $sandbox): string
    {
        if ($sandbox && $this->base_url_sandbox) {
            return $this->base_url_sandbox;
        }
        if (! $sandbox && $this->base_url_live) {
            return $this->base_url_live;
        }

        return $this->base_url ?? '';
    }
}
