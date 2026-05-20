<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AdminMediaService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait HandlesAdminMedia
{
    protected function mediaFileRules(string $profile, string $field = 'file'): array
    {
        $service = app(AdminMediaService::class);
        $mimes = implode(',', $service->mimesRule($profile));
        $max = $service->maxKb($profile);

        $isImage = in_array('png', $service->mimesRule($profile), true)
            && ! in_array('pdf', $service->mimesRule($profile), true);

        return [
            $field => [
                'nullable',
                $isImage ? 'image' : 'file',
                'mimes:'.$mimes,
                'max:'.$max,
            ],
            'remove_'.$field => ['nullable', 'boolean'],
        ];
    }

    protected function resolveMediaUrl(
        Request $request,
        string $fileField,
        string $removeField,
        ?string $existingUrl,
        string $profile,
        ?string $namePrefix = null
    ): ?string {
        $service = app(AdminMediaService::class);

        if ($request->boolean($removeField)) {
            $service->deleteIfStored($existingUrl);

            return null;
        }

        if ($request->hasFile($fileField)) {
            try {
                $url = $service->store($request->file($fileField), $profile, $namePrefix);
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    $fileField => [$e->getMessage() ?: 'Upload failed.'],
                ]);
            }

            $service->deleteIfStored($existingUrl);

            return $url;
        }

        return $existingUrl;
    }
}
