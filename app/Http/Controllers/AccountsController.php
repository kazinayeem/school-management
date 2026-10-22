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
        $this->admin($request);
        $search = trim((string) $request->query('search', ''));

        $parents = DB::table('users')->where('role', 'parent')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')->limit(500)->get(['id', 'name', 'username', 'email', 'phone', 'is_active']);

        $children = DB::table('guardian_student')->join('users', 'users.id', '=', 'guardian_student.student_id')->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->whereIn('guardian_id', $parents->pluck('id'))->get(['guardian_id', 'users.id', 'users.name', 'student_profiles.admission_no', 'student_profiles.class_name', 'student_profiles.section'])->groupBy('guardian_id');

        return Inertia::render('office/parents', [
            ...$this->shared(),
            'rows' => $parents->map(fn ($p) => (array) $p + ['children' => ($children[$p->id] ?? collect())->values()]),
            'search' => $search,
        ]);
    }

    public function storeParent(Request $request): RedirectResponse
    {
        $this->admin($request);
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
        $this->admin($request);
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

    // ---- all user accounts

    public function users(Request $request): Response
    {
        $this->admin($request);
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');

        $rows = DB::table('users')
            ->when(in_array($role, ['admin', 'staff', 'student', 'parent'], true), fn ($q) => $q->where('role', $role))
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
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:8', 'max:100'],
            'children' => ['nullable', 'string', 'max:500'],
        ]);
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
        return ['navigation' => OfficeMenu::for('admin'), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
