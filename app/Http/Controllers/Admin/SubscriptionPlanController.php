<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::query()
            ->withCount('tenants')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('admin.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = ! empty($validated['slug'])
            ? $validated['slug']
            : SubscriptionPlan::uniqueSlug($validated['name']);

        SubscriptionPlan::create($validated);

        return redirect()->route('admin.plans.index')->with('success', 'Subscription plan created.');
    }

    public function edit(SubscriptionPlan $plan): View
    {
        $plan->loadCount('tenants');

        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $this->validated($request, $plan->id);

        if (empty($validated['slug'])) {
            $validated['slug'] = $plan->slug;
        }

        $plan->update($validated);

        return back()->with('success', 'Subscription plan updated.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->tenants()->exists()) {
            return back()->with('error', 'Cannot delete a plan assigned to companies. Deactivate it instead.');
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', 'Subscription plan deleted.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:subscription_plans,slug,'.($ignoreId ?? 'NULL')],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'interval' => ['required', 'in:monthly,yearly'],
            'features_text' => ['nullable', 'string'],
            'limits_json' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $validated['features'] = $this->parseLines($validated['features_text'] ?? '');
        unset($validated['features_text']);

        $validated['limits'] = $this->parseJson($validated['limits_json'] ?? null);
        unset($validated['limits_json']);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    protected function parseLines(string $text): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));

        return $lines === [] ? null : $lines;
    }

    protected function parseJson(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw ValidationException::withMessages([
                'limits_json' => ['Invalid JSON. Example: {"users": 10}'],
            ]);
        }

        return $decoded;
    }
}
