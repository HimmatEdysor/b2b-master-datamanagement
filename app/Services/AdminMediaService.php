<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminMediaService
{
    public function store(UploadedFile $file, string $profile, ?string $prefix = null): string
    {
        $cfg = config("media.{$profile}");
        if (! is_array($cfg)) {
            throw new \InvalidArgumentException("Unknown media profile: {$profile}");
        }

        $directory = trim((string) ($cfg['directory'] ?? 'uploads'), '/');
        $folder = $directory.'/'.date('Y/m');
        $ext = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $filename = ($prefix ? Str::slug($prefix).'_' : '').Str::uuid().'.'.strtolower($ext);

        $path = $file->storeAs($folder, $filename, 'public');

        return asset('storage/'.$path);
    }

    public function deleteIfStored(?string $url): void
    {
        if ($url === null || trim($url) === '') {
            return;
        }

        $storageUrl = asset('storage/');
        if (! str_starts_with($url, $storageUrl)) {
            return;
        }

        $relative = ltrim(str_replace($storageUrl, '', $url), '/');
        if ($relative !== '') {
            Storage::disk('public')->delete($relative);
        }
    }

    /** @return array<int, string> */
    public function mimesRule(string $profile): array
    {
        $mimes = config("media.{$profile}.mimes", []);

        return is_array($mimes) ? $mimes : [];
    }

    public function maxKb(string $profile): int
    {
        return (int) config("media.{$profile}.max_kb", 5120);
    }
}
