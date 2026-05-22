<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MasterActivityLogService;
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
}
