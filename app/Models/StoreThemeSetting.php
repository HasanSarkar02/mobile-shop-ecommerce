<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StoreThemeSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'store_theme_settings';

    protected $fillable = [
        'tenant_id', 'logo_path', 'favicon_path', 'primary_color', 'secondary_color', 'font_family', 'social_links', 'footer_text',
    ];

    protected function casts(): array
    {
        return ['social_links' => 'array'];
    }
}
