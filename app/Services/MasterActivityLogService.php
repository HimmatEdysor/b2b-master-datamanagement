<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MasterActivityLogService
{
    public function log(
        string $channel,
        string $action,
        string $status,
        string $message,
        ?Tenant $tenant = null,
        ?User $user = null,
        array $meta = [],
    ): void {
        if (! $this->isValidChannel($channel)) {
            return;
        }

        $user ??= Auth::user();
        $timestamp = now()->format('Y-m-d H:i:s');
        $parts = [
            "[{$timestamp}]",
            "status={$status}",
            "action={$action}",
        ];

        if ($tenant) {
            $parts[] = 'tenant_id='.$tenant->id;
            $parts[] = 'slug='.$tenant->slug;
            $parts[] = 'tenant='.Str::limit($tenant->name, 80, '');
        }

        if ($user) {
            $parts[] = 'user_id='.$user->id;
            $parts[] = 'user='.Str::limit($user->email ?? $user->name ?? 'user', 80, '');
        }

        $parts[] = 'message='.str_replace(["\n", "\r"], ' ', $message);

        if ($meta !== []) {
            $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $parts[] = 'meta='.$encoded;
            }
        }

        $line = implode(' | ', $parts).PHP_EOL;
        $path = $this->filePath($channel, now()->format('Y-m-d'));

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    public function database(
        string $action,
        string $status,
        string $message,
        ?Tenant $tenant = null,
        ?User $user = null,
        array $meta = [],
    ): void {
        $this->log('database', $action, $status, $message, $tenant, $user, $meta);
    }

    public function s3(
        string $action,
        string $status,
        string $message,
        ?Tenant $tenant = null,
        ?User $user = null,
        array $meta = [],
    ): void {
        $this->log('s3', $action, $status, $message, $tenant, $user, $meta);
    }

    public function domain(
        string $action,
        string $status,
        string $message,
        ?Tenant $tenant = null,
        ?User $user = null,
        array $meta = [],
    ): void {
        $this->log('domain', $action, $status, $message, $tenant, $user, $meta);
    }

    public function dns(
        string $action,
        string $status,
        string $message,
        ?Tenant $tenant = null,
        ?User $user = null,
        array $meta = [],
    ): void {
        $this->log('dns', $action, $status, $message, $tenant, $user, $meta);
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function channels(): array
    {
        return config('master_logs.channels', []);
    }

    public function isValidChannel(string $channel): bool
    {
        return array_key_exists($channel, $this->channels());
    }

    /**
     * @return list<string> Dates (Y-m-d), newest first
     */
    public function datesForChannel(string $channel): array
    {
        if (! $this->isValidChannel($channel)) {
            return [];
        }

        $dir = $this->channelDirectory($channel);
        if (! is_dir($dir)) {
            return [];
        }

        $dates = [];
        foreach (glob($dir.'/*.log') ?: [] as $file) {
            $base = basename($file, '.log');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $base)) {
                $dates[] = $base;
            }
        }

        rsort($dates);

        return $dates;
    }

    public function readLog(string $channel, string $date): string
    {
        if (! $this->isValidChannel($channel) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return '';
        }

        $path = $this->filePath($channel, $date);
        if (! is_file($path)) {
            return '';
        }

        $max = config('master_logs.max_view_lines', 2000);
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return '';
        }

        if (count($lines) > $max) {
            $lines = array_slice($lines, -$max);
            array_unshift($lines, '--- Showing last '.$max.' lines ---');
        }

        return implode("\n", array_reverse($lines));
    }

    public function fileSize(string $channel, string $date): ?int
    {
        if (! $this->isValidChannel($channel) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $path = $this->filePath($channel, $date);

        return is_file($path) ? (int) filesize($path) : null;
    }

    public function defaultDateForChannel(string $channel, ?string $requested): string
    {
        if ($requested && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested)) {
            $path = $this->filePath($channel, $requested);
            if (is_file($path)) {
                return $requested;
            }
        }

        $dates = $this->datesForChannel($channel);

        return $dates[0] ?? now()->format('Y-m-d');
    }

    protected function filePath(string $channel, string $date): string
    {
        return $this->channelDirectory($channel).DIRECTORY_SEPARATOR.$date.'.log';
    }

    protected function channelDirectory(string $channel): string
    {
        $base = rtrim((string) config('master_logs.path'), DIRECTORY_SEPARATOR);

        return $base.DIRECTORY_SEPARATOR.$channel;
    }

    public function formatFileSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }
}
