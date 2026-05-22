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

    public function fileExists(string $channel, string $date): bool
    {
        if (! $this->isValidChannel($channel) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        return is_file($this->filePath($channel, $date));
    }

    /**
     * All log files on disk, newest first.
     *
     * @return list<array{
     *     channel: string,
     *     date: string,
     *     filename: string,
     *     channel_label: string,
     *     size: int,
     *     size_label: string,
     *     modified: int
     * }>
     */
    public function listAllLogFiles(): array
    {
        $files = [];

        foreach ($this->channels() as $channel => $info) {
            foreach ($this->datesForChannel($channel) as $date) {
                $path = $this->filePath($channel, $date);
                $size = is_file($path) ? (int) filesize($path) : 0;

                $files[] = [
                    'channel' => $channel,
                    'date' => $date,
                    'filename' => $date.'.log',
                    'channel_label' => $info['label'] ?? ucfirst($channel),
                    'size' => $size,
                    'size_label' => $this->formatFileSize($size),
                    'modified' => is_file($path) ? (int) filemtime($path) : 0,
                ];
            }
        }

        usort($files, function (array $a, array $b): int {
            $byTime = $b['modified'] <=> $a['modified'];

            return $byTime !== 0 ? $byTime : strcmp($b['date'], $a['date']);
        });

        return $files;
    }

    /**
     * @return array{channel: string, date: string}|null
     */
    public function resolveSelection(?string $channel, ?string $date, array $allFiles): ?array
    {
        if ($channel && $date && $this->fileExists($channel, $date)) {
            return ['channel' => $channel, 'date' => $date];
        }

        if ($allFiles === []) {
            return null;
        }

        if ($channel) {
            foreach ($allFiles as $file) {
                if ($file['channel'] === $channel) {
                    return ['channel' => $file['channel'], 'date' => $file['date']];
                }
            }
        }

        return [
            'channel' => $allFiles[0]['channel'],
            'date' => $allFiles[0]['date'],
        ];
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

    public function readLog(string $channel, string $date, bool $newestFirst = true): string
    {
        if (! $this->fileExists($channel, $date)) {
            return '';
        }

        $path = $this->filePath($channel, $date);
        $maxBytes = (int) config('master_logs.max_view_bytes', 2_097_152);
        $maxLines = (int) config('master_logs.max_view_lines', 5000);

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return '';
        }

        if (strlen($raw) > $maxBytes) {
            $raw = '--- File truncated (showing last '.round($maxBytes / 1024).' KB) ---'."\n"
                .substr($raw, -$maxBytes);
        }

        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $lines = array_values(array_filter($lines, fn ($line) => $line !== null));

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, -$maxLines);
            array_unshift($lines, '--- Showing last '.$maxLines.' lines ---');
        }

        if ($newestFirst) {
            $lines = array_reverse($lines);
        }

        return implode("\n", $lines);
    }

    public function fileSize(string $channel, string $date): ?int
    {
        if (! $this->isValidChannel($channel) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $path = $this->filePath($channel, $date);

        return is_file($path) ? (int) filesize($path) : null;
    }

    public function storagePathLabel(): string
    {
        return 'storage/logs/master-activity/';
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
