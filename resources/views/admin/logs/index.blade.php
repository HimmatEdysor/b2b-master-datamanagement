@extends('layouts.admin')

@section('title', 'Activity logs')
@section('page-title', 'Activity logs')

@section('content')
<div class="logs-page">
    <p class="page-lead">Per-day log files for database, S3, domain, and DNS operations. Newest entries appear first when viewing a day.</p>

    <div class="logs-layout">
        <nav class="logs-channel-nav card" aria-label="Log categories">
            <h2 class="logs-nav-title">Categories</h2>
            <ul class="logs-channel-list">
                @foreach($channels as $key => $info)
                    <li>
                        <a href="{{ route('admin.logs.index', ['channel' => $key, 'date' => $key === $channel ? $date : null]) }}"
                           class="logs-channel-link {{ $key === $channel ? 'active' : '' }}">
                            <span class="logs-channel-label">{{ $info['label'] }}</span>
                            <span class="logs-channel-desc">{{ $info['description'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <aside class="logs-dates-panel card" aria-label="Log dates">
            <h2 class="logs-nav-title">Days</h2>
            @if($dates === [])
                <p class="logs-empty-hint">No log files yet for this category.</p>
            @else
                <ul class="logs-date-list">
                    @foreach($dates as $day)
                        <li>
                            <a href="{{ route('admin.logs.index', ['channel' => $channel, 'date' => $day]) }}"
                               class="logs-date-link {{ $day === $date ? 'active' : '' }}">
                                {{ \Carbon\Carbon::parse($day)->format('d M Y') }}
                                <span class="logs-date-raw">{{ $day }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>

        <div class="logs-viewer card">
            <div class="logs-viewer-header">
                <div>
                    <h2 class="logs-nav-title" style="margin:0">{{ $channels[$channel]['label'] ?? ucfirst($channel) }}</h2>
                    <p class="logs-viewer-meta">
                        <code>{{ $date }}.log</code>
                        @if($fileSize !== null)
                            · {{ $fileSizeLabel }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('admin.logs.index', ['channel' => $channel, 'date' => $date]) }}" class="btn btn-outline btn-sm">Refresh</a>
            </div>

            @if($content === '')
                <p class="logs-empty-hint">No entries for this day. Operations will be logged here when they run.</p>
            @else
                <pre class="logs-viewer-content" tabindex="0">{{ $content }}</pre>
            @endif
        </div>
    </div>
</div>
@endsection
