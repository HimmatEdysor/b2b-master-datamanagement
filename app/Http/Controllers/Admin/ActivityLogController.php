<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MasterActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __construct(
        protected MasterActivityLogService $logs,
    ) {}

    public function index(Request $request): View
    {
        $channels = $this->logs->channels();
        $allFiles = $this->logs->listAllLogFiles();
        $filesByChannel = collect($allFiles)->groupBy('channel');

        $requestedChannel = $request->string('channel')->toString() ?: null;
        $requestedDate = $request->string('date')->toString() ?: null;

        $selection = $this->logs->resolveSelection($requestedChannel, $requestedDate, $allFiles);

        $channel = $selection['channel'] ?? null;
        $date = $selection['date'] ?? null;
        $content = ($channel && $date) ? $this->logs->readLog($channel, $date) : '';
        $fileSize = ($channel && $date) ? $this->logs->fileSize($channel, $date) : null;
        $fileSizeLabel = $this->logs->formatFileSize($fileSize);

        $logIndexUrl = route('admin.logs.index');
        $logViewUrl = ($channel && $date)
            ? route('admin.logs.index', ['channel' => $channel, 'date' => $date])
            : $logIndexUrl;

        $selectedLabel = ($channel && $date)
            ? ($channels[$channel]['label'] ?? ucfirst($channel)).' / '.$date.'.log'
            : null;

        $storagePathLabel = $this->logs->storagePathLabel();

        return view('admin.logs.index', compact(
            'channels',
            'allFiles',
            'filesByChannel',
            'channel',
            'date',
            'content',
            'fileSize',
            'fileSizeLabel',
            'logIndexUrl',
            'logViewUrl',
            'selectedLabel',
            'storagePathLabel',
        ));
    }

    public function destroy(Request $request, string $channel, string $date): RedirectResponse
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return redirect()->route('admin.logs.index')->with('error', 'Invalid log date.');
        }

        if (! $this->logs->isValidChannel($channel)) {
            return redirect()->route('admin.logs.index')->with('error', 'Invalid log channel.');
        }

        if (! $this->logs->fileExists($channel, $date)) {
            return redirect()->route('admin.logs.index', ['channel' => $channel])
                ->with('error', 'Log file not found or already deleted.');
        }

        if (! $this->logs->deleteLog($channel, $date)) {
            return redirect()->route('admin.logs.index', ['channel' => $channel, 'date' => $date])
                ->with('error', 'Could not delete the log file.');
        }

        $label = ($this->logs->channels()[$channel]['label'] ?? $channel).' / '.$date.'.log';

        return redirect()
            ->route('admin.logs.index')
            ->with('success', 'Deleted log file: '.$label.'.');
    }
}
