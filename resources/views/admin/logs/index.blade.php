@extends('layouts.admin')

@section('title', 'Activity logs')
@section('page-title', 'Activity logs')

@section('content')
<div class="logs-page">
    <p class="page-lead">Log files from <code>{{ $logIndexUrl }}</code> — select a file on the left to view in the editor.</p>

    <div class="logs-workspace">
        <aside class="logs-file-sidebar" aria-label="Log files from storage">
            <div class="logs-file-sidebar-head">
                <h2 class="logs-nav-title">Log files</h2>
                <span class="logs-file-count">{{ count($allFiles) }} file{{ count($allFiles) === 1 ? '' : 's' }}</span>
            </div>
            <p class="logs-storage-path" title="Path on server"><code>{{ $storagePathLabel }}</code></p>

            @if($allFiles === [])
                <p class="logs-empty-hint">No log files in storage yet. Approve a company, change a domain, or run a migration to generate logs.</p>
            @else
                <div class="logs-file-tree">
                    @foreach($filesByChannel as $channelKey => $channelFiles)
                        @php
                            $channelInfo = $channels[$channelKey] ?? ['label' => ucfirst($channelKey)];
                        @endphp
                        <div class="logs-file-group">
                            <div class="logs-file-group-title">{{ $channelInfo['label'] }}</div>
                            <ul class="logs-file-list">
                                @foreach($channelFiles as $file)
                                    @php
                                        $isActive = $channel === $file['channel'] && $date === $file['date'];
                                    @endphp
                                    <li>
                                        <a href="{{ route('admin.logs.index', ['channel' => $file['channel'], 'date' => $file['date']]) }}"
                                           class="logs-file-item {{ $isActive ? 'is-active' : '' }}">
                                            <span class="logs-file-name">{{ $file['filename'] }}</span>
                                            <span class="logs-file-meta">{{ $file['size_label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </aside>

        <section class="logs-editor-panel" aria-label="Log file viewer">
            @if($channel && $date)
                <header class="logs-editor-toolbar">
                    <div class="logs-editor-toolbar-info">
                        <strong class="logs-editor-title">{{ $selectedLabel }}</strong>
                        <span class="logs-editor-meta">
                            <code>{{ $channel }}/{{ $date }}.log</code>
                            @if($fileSize !== null)
                                · {{ $fileSizeLabel }}
                            @endif
                        </span>
                    </div>
                    <div class="logs-editor-actions">
                        @include('admin.partials.copy-btn', ['text' => $content, 'title' => 'Copy log content', 'label' => 'Copy'])
                        <a href="{{ route('admin.logs.index', ['channel' => $channel, 'date' => $date]) }}" class="btn btn-outline btn-sm">Refresh</a>
                    </div>
                </header>

                @if($content === '')
                    <p class="logs-empty-hint logs-editor-empty">This file exists but has no entries yet.</p>
                @endif

                <textarea class="logs-text-editor"
                          readonly
                          spellcheck="false"
                          aria-label="Log file content for {{ $selectedLabel }}">{{ $content }}</textarea>
            @else
                <div class="logs-editor-placeholder">
                    <p class="logs-empty-hint">Select a log file from the left to view its contents.</p>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const editor = document.querySelector('.logs-text-editor');
    if (!editor) return;
    editor.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            e.preventDefault();
            editor.select();
        }
    });
})();
</script>
@endpush
