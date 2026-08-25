<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ThemePresets;
use Illuminate\Database\Eloquent\Model;

class StoreThemeSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'store_theme_settings';

    protected $fillable = [
        'tenant_id', 'logo_path', 'favicon_path', 'primary_color', 'secondary_color', 'font_family', 'social_links', 'whatsapp_widget_enabled', 'footer_text',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'whatsapp_widget_enabled' => 'boolean',
        ];
    }

    /**
     * Resolved brand (primary) color — falls back to general preset.
     */
    public function brandColor(): string
    {
        $raw = $this->getAttribute('primary_color');
        $value = is_string($raw) && $raw !== '' ? $raw : null;

        return $value ?? ThemePresets::primaryFor('general');
    }

    /**
     * Resolved secondary color — fallback chain: secondary → primary → general secondary.
     */
    public function brandSecondaryColor(): string
    {
        $secondaryRaw = $this->getAttribute('secondary_color');
        $secondary = is_string($secondaryRaw) && $secondaryRaw !== '' ? $secondaryRaw : null;

        if ($secondary !== null) {
            return $secondary;
        }

        $primaryRaw = $this->getAttribute('primary_color');
        $primary = is_string($primaryRaw) && $primaryRaw !== '' ? $primaryRaw : null;

        if ($primary !== null) {
            return $primary;
        }

        return ThemePresets::secondaryFor('general');
    }

    /**
     * CSS font-stack for the stored font_family key (backward-compatible).
     */
    public function fontStack(): string
    {
        $raw = $this->getAttribute('font_family');

        return ThemePresets::fontStack(is_string($raw) ? $raw : null);
    }
}
