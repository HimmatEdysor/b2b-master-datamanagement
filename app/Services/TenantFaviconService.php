<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TenantFaviconService
{
    public function store(UploadedFile $file, ?string $slug = null): string
    {
        $maxKb = (int) config('website.favicon.max_upload_kb', 512);
        if ($file->getSize() > $maxKb * 1024) {
            throw new RuntimeException('Favicon file is too large.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'ico', 'svg'], true)) {
            throw new RuntimeException('Favicon must be PNG, ICO, SVG, or WebP.');
        }

        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            return $this->storeRasterFavicon($file, $slug, $ext);
        }

        $folder = 'tenant-favicons/'.date('Y/m');
        $filename = ($slug ? Str::slug($slug).'_' : '').Str::uuid().'.'.$ext;
        $relativePath = $folder.'/'.$filename;
        Storage::disk('public')->put($relativePath, file_get_contents($file->getRealPath()));

        return asset('storage/'.$relativePath);
    }

    protected function storeRasterFavicon(UploadedFile $file, ?string $slug, string $ext): string
    {
        $size = (int) config('website.favicon.output_size', 64);
        $processed = $this->resizeSquare($file->getRealPath(), $size, $ext);

        $folder = 'tenant-favicons/'.date('Y/m');
        $filename = ($slug ? Str::slug($slug).'_' : '').Str::uuid().'.png';
        $relativePath = $folder.'/'.$filename;
        Storage::disk('public')->put($relativePath, $processed);

        return asset('storage/'.$relativePath);
    }

    public function deleteIfStored(?string $url): void
    {
        if ($url === null || $url === '') {
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

    protected function resizeSquare(string $sourcePath, int $size, string $ext): string
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('Invalid favicon image.');
        }

        [$srcW, $srcH, $type] = $info;

        $src = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                ? imagecreatefromwebp($sourcePath)
                : throw new RuntimeException('WebP is not supported on this server.'),
            default => throw new RuntimeException('Unsupported favicon image type.'),
        };

        if ($src === false) {
            throw new RuntimeException('Could not read favicon image.');
        }

        $dest = imagecreatetruecolor($size, $size);
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefilledrectangle($dest, 0, 0, $size, $size, $transparent);

        $scale = min($size / $srcW, $size / $srcH);
        $newW = (int) round($srcW * $scale);
        $newH = (int) round($srcH * $scale);
        $dstX = (int) round(($size - $newW) / 2);
        $dstY = (int) round(($size - $newH) / 2);

        imagecopyresampled($dest, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($src);

        ob_start();
        imagepng($dest, null, 9);
        $png = ob_get_clean();
        imagedestroy($dest);

        if ($png === false) {
            throw new RuntimeException('Could not process favicon.');
        }

        return $png;
    }
}
