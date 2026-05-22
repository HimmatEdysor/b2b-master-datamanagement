@extends('layouts.admin')

@section('title', 'Web settings')
@section('page-title', 'Web settings')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">
        Configure tenant URLs, DNS, database provisioning, and CRM defaults here instead of editing <code>.env</code>.
        Saved values override environment variables. Leave a field empty and save to fall back to <code>.env</code> again.
    </p>
</div>

<div class="card admin-form-card master-settings-env-card">
    <h2 class="tenant-detail-heading">Still configured in <code>.env</code> only</h2>
    <p class="form-hint">Secrets and infrastructure keys stay in the environment file for security.</p>
    <table class="detail-table">
        @foreach($envOnly as $envKey => $label)
            <tr>
                <th scope="row">{{ $label }}</th>
                <td><code>{{ $envKey }}</code>
                    @if(env($envKey))
                        <span class="badge badge-active">set</span>
                    @else
                        <span class="badge badge-pending">not set</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" class="admin-form master-settings-form">
    @csrf
    @method('PUT')

    @foreach($sections as $sectionKey => $section)
        <div class="card admin-form-card master-settings-section">
            <h2 class="tenant-detail-heading">{{ $section['label'] }}</h2>
            @if(! empty($section['description']))
                <p class="form-hint section-lead">{{ $section['description'] }}</p>
            @endif

            @foreach($formState as $fieldKey => $state)
                @if(($state['definition']['section'] ?? '') !== $sectionKey)
                    @continue
                @endif
                @php
                    $def = $state['definition'];
                    $type = $def['type'];
                    $name = $fieldKey;
                    $id = 'setting-'.$name;
                @endphp
                <div class="form-group">
                    <label for="{{ $id }}">
                        {{ $def['label'] }}
                        @if($state['source'] === 'database')
                            <span class="badge badge-active badge-sm">saved</span>
                        @else
                            <span class="badge badge-draft badge-sm">from env</span>
                        @endif
                    </label>

                    @if($type === 'boolean')
                        <label class="checkbox-label">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1"
                                   @checked(old($name, $state['value']))>
                            Enable
                        </label>
                    @elseif($type === 'password')
                        <input type="password" id="{{ $id }}" name="{{ $name }}" class="form-control" autocomplete="new-password"
                               placeholder="{{ \App\Models\MasterSetting::query()->where('key', $name)->exists() ? '•••••••• (leave blank to keep)' : 'From .env if empty' }}">
                    @elseif($type === 'select')
                        <select id="{{ $id }}" name="{{ $name }}" class="form-control">
                            @foreach($def['options'] ?? [] as $optValue => $optLabel)
                                <option value="{{ $optValue }}" @selected(old($name, (string) ($state['value'] ?? '')) === (string) $optValue)>{{ $optLabel }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" id="{{ $id }}" name="{{ $name }}" class="form-control"
                               value="{{ old($name, is_array($state['value']) ? implode(', ', $state['value']) : (string) ($state['value'] ?? '')) }}"
                               autocomplete="off" spellcheck="false">
                    @endif

                    @if(! empty($def['hint']))
                        <p class="form-hint">{{ $def['hint'] }}</p>
                    @endif
                    @error($name)<p class="field-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="form-actions master-settings-actions">
        <button type="submit" class="btn btn-primary">Save web settings</button>
    </div>
</form>
@endsection
