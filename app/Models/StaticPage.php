<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StaticPageStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StaticPage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['title', 'slug', 'content', 'status', 'show_in_footer', 'footer_group', 'meta_title', 'meta_description'];

    protected function casts(): array
    {
        return [
            'status' => StaticPageStatus::class,
            'show_in_footer' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            $page->slug ??= Str::slug($page->title);
        });
    }
}