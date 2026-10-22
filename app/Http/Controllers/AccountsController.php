<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountsController extends Controller
{
    public static function uniqueUsername(string $name): string
    {
        $base = Str::slug($name, '.') ?: 'user';
        $username = $base;
        $n = 1;
        while (DB::table('users')->where('username', $username)->exists()) {
            $username = $base.'.'.(++$n);
        }

        return $username;
    }

    // ---- parents

    public function parents(Request $request): Response
    {
        \App\Support\Permissions::office($request);
        $search = trim((string) $request->query('search', ''));

        $parents = DB::table('users')->where('role', 'parent')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')->limit(500)->get(['id', 'name', 'username', 'email', 'phone', 'is_active']);

        $children = DB::table('guardian_student')->join('users', 'users.id', '=', 'guardian_student.student_id')->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->whereIn('guardian_id', $parents->pluck('id'))->get(['guardian_id', 'users.id', 'users.name', 'users.photo', 'student_profiles.admission_no', 'student_profiles.roll_no', 'student_profiles.class_name', 'student_profiles.section'])->map(function ($c) {
                $c->photo_url = \App\Support\Photos::url($c->photo);

                return $c;
            })->groupBy('guardian_id');

        return Inertia::render('office/parents', [
            ...$this->shared(),
            'rows' => $parents->map(fn ($p) => (array) $p + ['children' => ($children[$p->id] ?? collect())->values()]),
            'search' => $search,
            'classes' => DB::table('academic_classes')->orderBy('sort_order')->pluck('name'),
        ]);
    }

    public function storeParent(Request $request): RedirectResponse
    {
        \App\Support\Permissions::office($request);
        $data = $this->parentData($request, null);
        $links = $this->resolveChildren($data['children'] ?? '');
        if (isset($links['error'])) {
            return back()->withErrors(['children' => $links['error']]);
        }

        DB::transaction(function () use ($data, $links) {
            $username = $data['username'] ?: self::uniqueUsername($data['name']);
            $user = User::create(['name' => $data['name'], 'username' => $username, 'email' => $data['email'] ?: "{$username}@borno.test", 'phone' => $data['phone'] ?? null, 'role' => 'parent', 'password' => $data['password'], 'is_active' => true]);
            DB::table('guardian_student')->insert(array_map(fn ($sid) => ['guardian_id' => $user->id, 'student_id' => $sid], $links));
        });
        Audit::log('created', 'Parent account', $data['name']);

        return back()->with('status', 'Parent account created.');
    }

    public function updateParent(Request $request, int $id): RedirectResponse
    {
        \App\Support\Permissions::office($request);
        $user = User::where('role', 'parent')->findOrFail($id);
        $data = $this->parentData($request, $id);
        $links = $this->resolveChildren($data['children'] ?? '');
        if (isset($links['error'])) {
            return back()->withErrors(['children' => $links['error']]);
        }

        DB::transaction(function () use ($user, $data, $links, $id) {
            $user->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null]);
            foreach (['username', 'email'] as $field) {
                if (filled($data[$field] ?? null)) {
                    $user->{$field} = $data[$field];
                }
            }
            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }
            $user->save();
            DB::table('guardian_student')->where('guardian_id', $id)->delete();
            DB::table('guardian_student')->insert(array_map(fn ($sid) => ['guardian_id' => $id, 'student_id' => $sid], $links));
        });
        Audit::log('updated', 'Parent account', $user->name);

        return back()->with('status', 'Parent account updated.');
    }

    /**
     * Live student lookup used by every student picker. With nothing typed it still returns
     * a few students; a name, admission (registration) number, roll or username narrows it,
     * and class and section narrow it further. scope=library searches library card holders.
     */
    public function studentSearch(Request $request): \Illuminate\Http\JsonResponse
    {
        \App\Support\Permissions::office($request);
        $q = trim((string) $request->query('q', ''));
        $class = (string) $request->query('class', '');
        $section = (string) $request->query('section', '');
        $library = $request->query('scope') === 'library';

        $rows = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->when($library, fn ($query) => $query->join('library_members', 'library_members.user_id', '=', 'users.id')->where('library_members.active', true))
            ->where('student_profiles.status', 'active')
            ->tap(fn ($query) => \App\Support\TeacherScope::restrict($query, $request->user()))
            ->when($class !== '', fn ($query) => $query->where('student_profiles.class_name', $class))
            ->when(in_array($section, ['A', 'B'], true), fn ($query) => $query->where('student_profiles.section', $section))
            ->when($q !== '', fn ($query) => $query->where(fn ($query) => $query->where('users.name', 'like', "%{$q}%")->orWhere('student_profiles.admission_no', 'like', "%{$q}%")->orWhere('users.username', 'like', "%{$q}%")->orWhere('student_profiles.roll_no', $q)
                ->when($library, fn ($query) => $query->orWhere('library_members.card_no', 'like', "%{$q}%"))))
            ->orderByRaw('cast(substr(student_profiles.class_name, 7) as integer)')->orderBy('student_profiles.section')->orderByRaw('cast(student_profiles.roll_no as integer)')
            ->limit($q === '' && $class === '' ? 8 : 15)
            ->get(['users.id', 'users.name', 'users.photo', 'student_profiles.admission_no', 'student_profiles.roll_no', 'student_profiles.class_name', 'student_profiles.section'] + ($library ? [6 => 'library_members.card_no'] : []));

        $linked = DB::table('guardian_student')->whereIn('student_id', $rows->pluck('id'))->selectRaw('student_id, count(*) as total')->groupBy('student_id')->pluck('total', 'student_id');

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'admission_no' => $r->admission_no, 'roll_no' => $r->roll_no, 'class_name' => $r->class_name, 'section' => $r->section,
                'card_no' => $r->card_no ?? null, 'photo' => \App\Support\Photos::url($r->photo), 'parents' => (int) ($linked[$r->id] ?? 0),
            ])->values(),
            'classes' => DB::table('academic_classes')->orderBy('sort_order')->pluck('name'),
        ]);
    }

    // ---- all user accounts

    public function users(Request $request): Response
    {
        $this->admin($request);
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');

        $rows = DB::table('users')
            ->when(in_array($role, ['admin', 'teacher', 'staff', 'student', 'parent'], true), fn ($q) => $q->where('role', $role))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('role')->orderBy('name')->limit(300)->get(['id', 'name', 'username', 'email', 'role', 'is_active']);

        return Inertia::render('office/users', [
            ...$this->shared(),
            'rows' => $rows,
            'counts' => DB::table('users')->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
            'me' => $request->user()->id,
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);
        $data['email'] ??= null;
        User::create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'] ?: "{$data['username']}@borno.test", 'role' => 'admin', 'password' => $data['password'], 'is_active' => true]);
        Audit::log('created', 'Admin account', $data['name']);

        return back()->with('status', 'Administrator added.');
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        if ($user->is_active && $user->role === 'admin' && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'At least one administrator must stay active.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        Audit::log('updated', 'Account', $user->name.($user->is_active ? ' activated' : ' deactivated'));

        return back()->with('status', 'Account updated.');
    }

    public function password(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $user = User::findOrFail($id);
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:100']]);
        $user->update(['password' => $data['password']]);
        DB::table('sessions')->where('user_id', $id)->delete();
        Audit::log('updated', 'Account', $user->name.' password reset');

        return back()->with('status', 'Password changed.');
    }

    private function parentData(Request $request, ?int $id): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:8', 'max:100'],
            'children' => ['nullable', 'string', 'max:500'],
        ]);
        foreach (['username', 'email', 'phone', 'password', 'children'] as $optional) {
            $data[$optional] ??= null;
        }

        return $data;
    }

    /** @return list<int>|array{error: string} */
    private function resolveChildren(string $list): array
    {
        $numbers = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', $list) ?: []))));
        if ($numbers === []) {
            return [];
        }

        $found = DB::table('student_profiles')->whereIn('admission_no', $numbers)->pluck('user_id', 'admission_no');
        $missing = array_values(array_diff($numbers, $found->keys()->all()));
        if ($missing) {
            return ['error' => 'No student has the admission number '.implode(', ', $missing).'.'];
        }

        return $found->values()->map(fn ($v) => (int) $v)->all();
    }

    private function shared(): array
    {
        return ['navigation' => OfficeMenu::for(request()->user()->role), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
