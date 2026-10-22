<?php

namespace App\Http\Controllers;

use App\Support\OfficeMenu;
use App\Support\WebsiteResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    public function index(Request $request, string $resource): Response
    {
        $this->authorizeAdmin($request);

        $shared = [
            'navigation' => OfficeMenu::for('admin'),
            'roleLabel' => 'Super Admin',
        ];

        if ($resource === 'settings') {
            $fields = WebsiteResources::settingFields();
            $stored = DB::table('site_settings')->pluck('value', 'key');
            $values = [];
            foreach ($fields as $f) {
                $raw = $stored[$f['name']] ?? null;
                $values[$f['name']] = $f['type'] === 'checkbox'
                    ? ($raw === null ? $f['default'] : (bool) $raw)
                    : ($raw ?? $f['default']);
            }

            return Inertia::render('office/website', [
                ...$shared,
                'resource' => 'settings',
                'definition' => ['label' => 'Site Settings', 'subtitle' => 'Contact details and options used across the public website.', 'fields' => $fields],
                'values' => $values,
            ]);
        }

        $def = WebsiteResources::all()[$resource];
        [$col, $dir] = $def['order'];

        return Inertia::render('office/website', [
            ...$shared,
            'resource' => $resource,
            'definition' => collect($def)->only(['label', 'subtitle', 'columns', 'fields'])->all(),
            'rows' => DB::table($def['table'])->orderBy($col, $dir)->get(),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $def = WebsiteResources::all()[$resource];

        DB::table($def['table'])->insert($this->payload($request, $resource, $def) + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', "{$def['label']} entry added.");
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $def = WebsiteResources::all()[$resource];
        abort_unless(DB::table($def['table'])->where('id', $id)->exists(), 404);

        DB::table($def['table'])->where('id', $id)->update($this->payload($request, $resource, $def, $id) + ['updated_at' => now()]);

        return back()->with('status', "{$def['label']} entry updated.");
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        DB::table(WebsiteResources::all()[$resource]['table'])->where('id', $id)->delete();

        return back()->with('status', 'Entry deleted.');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $fields = WebsiteResources::settingFields();
        $data = $request->validate(collect($fields)->mapWithKeys(
            fn ($f) => [$f['name'] => $f['type'] === 'checkbox' ? 'boolean' : $f['rules']]
        )->all());

        foreach ($fields as $f) {
            $value = $f['type'] === 'checkbox' ? (int) ($data[$f['name']] ?? 0) : ($data[$f['name']] ?? '');
            DB::table('site_settings')->updateOrInsert(['key' => $f['name']], ['value' => (string) $value]);
        }

        return back()->with('status', 'Site settings saved.');
    }

    private function payload(Request $request, string $resource, array $def, ?int $id = null): array
    {
        $rules = [];
        foreach ($def['fields'] as $f) {
            $rules[$f['name']] = $f['type'] === 'checkbox' ? 'boolean' : $f['rules'];
        }
        if ($resource === 'pages') {
            $rules['slug'] = ['nullable', 'string', 'max:150', 'alpha_dash', Rule::unique('website_pages', 'slug')->ignore($id)];
        }

        $data = $request->validate($rules);

        if ($resource === 'pages' && blank($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['title']);
            if (DB::table('website_pages')->where('slug', $data['slug'])->where('id', '!=', $id ?? 0)->exists()) {
                $data['slug'] .= '-'.Str::lower(Str::random(4));
            }
        }

        $out = [];
        foreach ($def['fields'] as $f) {
            $value = $data[$f['name']] ?? null;
            $out[$f['name']] = match ($f['type']) {
                'checkbox' => (int) (bool) $value,
                'number' => (int) ($value ?? ($f['default'] ?? 0)),
                default => $value,
            };
        }

        return $out;
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
