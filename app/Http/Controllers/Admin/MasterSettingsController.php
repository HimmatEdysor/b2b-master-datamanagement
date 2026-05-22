<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MasterSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterSettingsController extends Controller
{
    public function __construct(
        protected MasterSettingsService $settings,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'sections' => $this->settings->sections(),
            'formState' => $this->settings->formState(),
            'envOnly' => $this->settings->envOnlyKeys(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach ($this->settings->fields() as $key => $field) {
            if ($key === 'dns_provider') {
                $rules[$key] = ['nullable', 'string', 'in:cloudflare,route53,manual'];

                continue;
            }

            $rules[$key] = match ($field['type']) {
                'boolean' => ['nullable', 'boolean'],
                'password' => ['nullable', 'string', 'max:500'],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        $validated = $request->validate($rules);

        foreach ($this->settings->fields() as $key => $field) {
            if ($field['type'] === 'boolean') {
                $validated[$key] = $request->boolean($key);
            }
        }

        $this->settings->save($validated);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Web settings saved. Values apply immediately (overrides .env when set).');
    }
}
