<?php

namespace App\Http\Controllers;

use App\Support\AcademicResources;
use App\Support\Audit;
use App\Support\OfficeMenu;
use App\Support\OperationsResources;
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
    /** Routes without a {resource} segment (such as /app/devices) fix the resource in the route defaults. */
    public function baseIndex(Request $request): Response
    {
        return $this->index($request, (string) $request->route('resource'));
    }

    public function baseStore(Request $request): RedirectResponse
    {
        return $this->store($request, (string) $request->route('resource'));
    }

    public function baseUpdate(Request $request, int $id): RedirectResponse
    {
        return $this->update($request, (string) $request->route('resource'), $id);
    }

    public function baseDestroy(Request $request, int $id): RedirectResponse
    {
        return $this->destroy($request, (string) $request->route('resource'), $id);
    }

    public function index(Request $request, string $resource): Response
    {
        $readOnly = $request->user()->role !== 'admin';
        if ($readOnly) {
            abort_unless($this->group($request) === 'academic' && OfficeMenu::allows($request->user()->role, "/app/academic/{$resource}"), 403);
        }

        $shared = [
            'navigation' => OfficeMenu::for($request->user()->role),
            'roleLabel' => $readOnly ? 'Teacher' : 'Super Admin',
            'readOnly' => $readOnly,
        ];

        if ($resource === 'settings') {
            abort_if($readOnly, 403);
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
                'baseUrl' => '/app/website/settings',
                'definition' => ['label' => 'Site Settings', 'subtitle' => 'Contact details and options used across the public website.', 'fields' => $fields],
                'values' => $values,
            ]);
        }

        $def = $this->defs($request)[$resource];
        [$col, $dir] = $def['order'];
        $fields = $this->withOptions($def['fields']);
        $query = isset($def['query']) ? $def['query']() : DB::table($def['table'])->orderBy($col, $dir);

        $rows = $query->get()->map(function ($row) use ($fields) {
            $row = (array) $row;
            foreach ($fields as $f) {
                if ($f['type'] === 'select' && isset($f['options'])) {
                    $match = collect($f['options'])->firstWhere('value', (string) ($row[$f['name']] ?? ''));
                    $row['__'.$f['name']] = $match['label'] ?? ($row[$f['name']] ?? '');
                }
            }

            return $row;
        });

        return Inertia::render('office/website', [
            ...$shared,
            'resource' => $resource,
            'baseUrl' => $def['base'] ?? '/app/'.$this->group($request).'/'.$resource,
            'definition' => ['fields' => $fields] + collect($def)->only(['label', 'subtitle', 'columns', 'column_labels'])->all(),
            'rows' => $rows,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $def = $this->defs($request)[$resource];

        $newId = DB::table($def['table'])->insertGetId($this->payload($request, $resource, $def) + ['created_at' => now(), 'updated_at' => now()]);
        $this->afterSave($resource, $newId);
        Audit::log('created', $def['label'], "#{$newId}");

        return back()->with('status', 'Entry added.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $def = $this->defs($request)[$resource];
        abort_unless(DB::table($def['table'])->where('id', $id)->exists(), 404);

        DB::table($def['table'])->where('id', $id)->update($this->payload($request, $resource, $def, $id) + ['updated_at' => now()]);
        $this->afterSave($resource, $id);
        Audit::log('updated', $def['label'], "#{$id}");

        return back()->with('status', 'Entry updated.');
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $def = $this->defs($request)[$resource];

        if (isset($def['delete_guard'])) {
            [$table, $column, $message, $via] = $def['delete_guard'] + [3 => 'id'];
            $key = $via === 'id' ? $id : DB::table($def['table'])->where('id', $id)->value($via);
            if (DB::table($table)->where($column, $key)->exists()) {
                return back()->with('error', $message);
            }
        }

        DB::table($def['table'])->where('id', $id)->delete();
        Audit::log('deleted', $def['label'], "#{$id}");
        if ($resource === 'vehicles') {
            \App\Support\TransportSync::refresh();
        }
        if ($resource === 'grading') {
            \App\Support\Grading::flush();
        }

        if ($resource === 'vehicles') {
            \App\Support\TransportSync::refresh();
        }

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
            $rules[$f['name']] = $f['type'] === 'checkbox' ? 'boolean' : array_map(function (string $rule) use ($id) {
                if (! str_starts_with($rule, 'unique:')) {
                    return $rule;
                }
                [$table, $column] = explode(',', substr($rule, 7));

                return Rule::unique($table, $column)->ignore($id);
            }, explode('|', $f['rules']));
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
                'select' => ($value === '' || $value === null) ? null : $value,
                'datetime' => $value === null ? null : str_replace('T', ' ', (string) $value),
                default => $value,
            };
        }

        if ($resource === 'members' && blank($out['card_no'] ?? null)) {
            $out['card_no'] = 'LIB-'.str_pad((string) ((int) DB::table('library_members')->max('id') + 1), 5, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $fields */
    private function withOptions(array $fields): array
    {
        return array_map(function (array $f) {
            if (! isset($f['options_query'])) {
                return $f;
            }
            $q = $f['options_query'];
            unset($f['options_query']);

            if ($q === 'people') {
                $f['options'] = DB::table('users')->whereIn('role', ['staff', 'student'])->orderBy('role')->orderBy('name')->limit(1500)->get(['id', 'name', 'role'])
                    ->map(fn ($u) => ['value' => (string) $u->id, 'label' => "{$u->name} ({$u->role})"])->all();
            } elseif ($q === 'staff') {
                $f['options'] = DB::table('users')->where('role', 'staff')->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name])->all();
            } elseif ($q === 'students') {
                $f['options'] = DB::table('users')
                    ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                    ->where('student_profiles.status', 'active')
                    ->orderBy('users.name')->limit(1000)
                    ->get(['users.id', 'users.name', 'student_profiles.admission_no'])
                    ->map(fn ($u) => ['value' => (string) $u->id, 'label' => "{$u->name} ({$u->admission_no})"])->all();
            } else {
                [$table, $value, $label, $blank] = $q + [3 => null];
                $f['options'] = [
                    ...($blank ? [['value' => '', 'label' => $blank]] : []),
                    ...DB::table($table)->orderBy($label)->get([$value, $label])->map(fn ($r) => ['value' => (string) $r->$value, 'label' => $r->$label])->all(),
                ];
            }

            return $f;
        }, $fields);
    }

    private function afterSave(string $resource, int $id): void
    {
        if ($resource === 'grading') {
            \App\Support\Grading::flush();
        }

        if ($resource === 'sessions' && DB::table('academic_sessions')->where('id', $id)->value('is_current')) {
            DB::table('academic_sessions')->where('id', '!=', $id)->update(['is_current' => false]);
        }
    }

    private function group(Request $request): string
    {
        return $request->route('group') ?? 'website';
    }

    /** @return array<string, array<string, mixed>> */
    private function defs(Request $request): array
    {
        return match ($this->group($request)) {
            'academic' => AcademicResources::all(),
            'website' => WebsiteResources::all(),
            default => OperationsResources::resources($this->group($request)),
        };
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
