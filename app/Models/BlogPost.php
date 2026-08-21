<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlogPostStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BlogPost extends Model implements HasMedia
{
    use BelongsToTenant;
    use InteractsWithMedia;

    protected $fillable = ['author_id', 'title', 'slug', 'excerpt', 'content', 'status', 'meta_title', 'meta_description', 'published_at'];

    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            $post->slug ??= Str::slug($post->title);
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function sanitizedContent(): string
    {
        return Purifier::clean($this->content);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('large')->width(1600)->fit(Fit::Max, 1600, 900)->format('webp');
    }
}
