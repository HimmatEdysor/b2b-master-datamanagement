@php
    $templateDb = config('master.template_database', 'template');
    $seedTables = config('master.tenant_seed_tables', []);
    $themeCols = config('master.tenant_web_setting_theme_columns', []);
@endphp

<div class="form-group clone-db-info">
    <p class="form-hint" style="margin-top:0">
        <strong>Database copy:</strong> table structure only from <code>{{ $templateDb }}</code>, then reference rows from these tables only
        (not applications, leads, messages, or other tenant data):
    </p>
    @if($seedTables !== [])
        <ul class="form-hint" style="margin:0.35rem 0 0 1.1rem">
            @foreach($seedTables as $table)
                <li><code>{{ $table }}</code></li>
            @endforeach
        </ul>
    @endif
    @if($themeCols !== [])
        <p class="form-hint" style="margin-bottom:0">
            Plus <code>web_settings</code> columns: {{ implode(', ', array_map(fn ($c) => "`{$c}`", $themeCols)) }}.
            Edit the list in <code>config/master.php</code> (<code>tenant_seed_tables</code>).
        </p>
    @endif
</div>
