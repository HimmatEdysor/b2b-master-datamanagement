<?php

namespace App\Support;

use App\Models\BlogPost;
use Illuminate\Support\Str;

class BlogSlug
{
    public const PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /**
     * Title or manual input → URL slug (spaces and underscores become hyphens).
     */
    public static function fromText(string $value): string
    {
        $slug = Str::slug(trim($value));

        return $slug !== '' ? $slug : 'post';
    }

    /**
     * Ensure slug is unique; if taken, append -2, -3, … then a short random id.
     */
    public static function unique(string $seed, ?int $ignoreId = null): string
    {
        $base = static::fromText($seed);
        $slug = $base;

        if (! static::exists($slug, $ignoreId)) {
            return $slug;
        }

        for ($i = 2; $i <= 99; $i++) {
            $candidate = $base.'-'.$i;
            if (! static::exists($candidate, $ignoreId)) {
                return $candidate;
            }
        }

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (static::exists($slug, $ignoreId));

        return $slug;
    }

    protected static function exists(string $slug, ?int $ignoreId): bool
    {
        return BlogPost::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }
}
