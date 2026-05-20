<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TenantLogoService
{
    public function store(UploadedFile $file, ?string $slug = null): string
    {
        $cfg = config('website.logo');
        $targetW = (int) $cfg['output_width'];
        $targetH = (int) $cfg['output_height'];

        $processed = $this->resizeToCanvas($file->getRealPath(), $targetW, $targetH);

        $folder = 'tenant-logos/'.date('Y/m');
        $filename = ($slug ? Str::slug($slug).'_' : '').Str::uuid().'.png';
        $relativePath = $folder.'/'.$filename;

        Storage::disk('public')->put($relativePath, $processed);

        return asset('storage/'.$relativePath);
    }

    public function deleteIfStored(?string $logoUrl): void
    {
        if ($logoUrl === null || $logoUrl === '') {
            return;
        }

        $storageUrl = asset('storage/');
        if (! str_starts_with($logoUrl, $storageUrl)) {
            return;
        }

        $relative = ltrim(str_replace($storageUrl, '', $logoUrl), '/');
        if ($relative !== '') {
            Storage::disk('public')->delete($relative);
        }
    }

    /**
     * Fit image into exact output dimensions (client should already crop to aspect ratio).
     */
    protected function resizeToCanvas(string $sourcePath, int $width, int $height): string
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('Invalid image file.');
        }

        [$srcW, $srcH, $type] = $info;

        $src = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                ? imagecreatefromwebp($sourcePath)
                : throw new RuntimeException('WebP is not supported on this server.'),
            default => throw new RuntimeException('Unsupported image type. Use JPEG, PNG, or WebP.'),
        };

        if ($src === false) {
            throw new RuntimeException('Could not read image.');
        }

        $dest = imagecreatetruecolor($width, $height);
        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefilledrectangle($dest, 0, 0, $width, $height, $transparent);

        $scale = min($width / $srcW, $height / $srcH);
        $newW = (int) round($srcW * $scale);
        $newH = (int) round($srcH * $scale);
        $dstX = (int) round(($width - $newW) / 2);
        $dstY = (int) round(($height - $newH) / 2);

        imagecopyresampled($dest, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($src);

        ob_start();
        imagepng($dest, null, 8);
        $png = ob_get_clean();
        imagedestroy($dest);

        if ($png === false) {
            throw new RuntimeException('Could not process image.');
        }

        return $png;
    }
}
