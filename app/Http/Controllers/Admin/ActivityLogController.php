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
        $channelKeys = array_keys($channels);
        $channel = $request->string('channel')->toString();

        if (! $this->logs->isValidChannel($channel)) {
            $channel = $channelKeys[0] ?? 'database';
        }

        $dates = $this->logs->datesForChannel($channel);
        $date = $this->logs->defaultDateForChannel($channel, $request->string('date')->toString() ?: null);
        $content = $this->logs->readLog($channel, $date);
        $fileSize = $this->logs->fileSize($channel, $date);
        $fileSizeLabel = $this->logs->formatFileSize($fileSize);

        return view('admin.logs.index', compact(
            'channels',
            'channel',
            'dates',
            'date',
            'content',
            'fileSize',
            'fileSizeLabel',
        ));
    }
}
