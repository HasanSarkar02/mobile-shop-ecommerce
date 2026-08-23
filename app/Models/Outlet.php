<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Outlet extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name', 'slug', 'address_line_1', 'address_line_2', 'city', 'phone', 'email',
        'opening_hours', 'latitude', 'longitude', 'map_url', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $outlet): void {
            $outlet->slug ??= Str::slug($outlet->name);
        });
    }

    public function fullAddress(): string
    {
        return collect([$this->address_line_1, $this->address_line_2, $this->city])
            ->filter()
            ->implode(', ');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function googleMapsUrl(): ?string
    {
        if ($this->map_url) {
            return $this->map_url;
        }

        if ($this->hasCoordinates()) {
            return 'https://www.google.com/maps?q='.(float) $this->latitude.','.(float) $this->longitude;
        }

        return null;
    }
}
