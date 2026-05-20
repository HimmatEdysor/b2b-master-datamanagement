<?php

namespace App\Models;

use App\Support\BlogSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'featured_image',
        'status',
        'published_at',
        'author_id',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public static function uniqueSlug(string $seed, ?int $ignoreId = null): string
    {
        return BlogSlug::unique($seed, $ignoreId);
    }

    public function seoTitle(): string
    {
        return trim((string) ($this->meta_title ?: $this->title));
    }

    public function seoDescription(): string
    {
        if (filled($this->meta_description)) {
            return Str::limit(strip_tags($this->meta_description), 160);
        }

        if (filled($this->excerpt)) {
            return Str::limit(strip_tags($this->excerpt), 160);
        }

        return Str::limit(strip_tags((string) $this->body), 160);
    }

    public function cardExcerpt(int $limit = 160): ?string
    {
        if (filled($this->excerpt)) {
            return Str::limit(strip_tags($this->excerpt), $limit);
        }

        if (filled($this->body)) {
            return Str::limit(strip_tags($this->body), $limit);
        }

        return null;
    }

    public function hasFeaturedImage(): bool
    {
        return filled($this->featured_image);
    }
}
