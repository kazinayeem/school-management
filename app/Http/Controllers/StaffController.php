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

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $this->admin($request);
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $type = in_array($request->query('type'), ['teacher', 'staff'], true) ? (string) $request->query('type') : '';

        $request->merge(['search' => $search, 'status' => $status, 'type' => $type]);
        $dataset = \App\Support\Listing\Datasets::find('staff');
        ['rows' => $rows, 'pagination' => $pagination] = \App\Support\Listing\Listing::page($dataset, $request);
        $rows = $rows->map(function ($r) {
            $r->photo_url = \App\Support\Photos::url($r->photo);

            return $r;
        });

        return Inertia::render('office/staff-list', [
            ...$this->shared(),
            'rows' => $rows,
            'counts' => ['active' => User::whereIn('role', ['teacher', 'staff'])->where('is_active', true)->count(), 'inactive' => User::whereIn('role', ['teacher', 'staff'])->where('is_active', false)->count(), 'teachers' => User::where('role', 'teacher')->count(), 'staff' => User::where('role', 'staff')->count()],
            'filters' => ['search' => $search, 'status' => $status, 'type' => $type, 'limit' => $pagination['limit'], 'sort' => $pagination['sort'], 'dir' => $pagination['dir']],
            'pagination' => $pagination,
            'exportColumns' => $dataset->columns(),
            'me' => $request->user()->id,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/staff-form', [...$this->shared(), 'staff' => null, 'files' => [], 'kinds' => \App\Support\UserFiles::KINDS]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->admin($request);
        $staff = DB::table('users')->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')->where('users.id', $id)->whereIn('users.role', ['teacher', 'staff'])
            ->first(['users.id', 'users.name', 'users.username', 'users.email', 'users.phone', 'users.is_active', 'users.role', 'users.photo', 'staff_profiles.employee_no', 'staff_profiles.designation', 'staff_profiles.department', 'staff_profiles.joined_on', 'staff_profiles.address']);
        abort_if($staff === null, 404);
        $staff->photo_url = \App\Support\Photos::url($staff->photo);

        return Inertia::render('office/staff-form', [...$this->shared(), 'staff' => $staff, 'files' => \App\Support\UserFiles::listFor($id), 'kinds' => \App\Support\UserFiles::KINDS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $this->validated($request, null);

        $id = DB::transaction(function () use ($data) {
            $username = $data['username'] ?: AccountsController::uniqueUsername($data['name']);
            $user = User::create([
                'name' => $data['name'], 'username' => $username, 'email' => $data['email'] ?: "{$username}@borno.test", 'phone' => $data['phone'] ?? null,
                'role' => $data['role'], 'password' => $data['password'], 'is_active' => $data['is_active'],
            ]);
            DB::table('staff_profiles')->insert([
                'user_id' => $user->id, 'employee_no' => $this->nextEmployeeNo(), 'designation' => $data['designation'] ?? null, 'department' => $data['department'] ?? null,
                'joined_on' => $data['joined_on'] ?? null, 'address' => $data['address'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $user->id;
        });
        \App\Support\UserFiles::saveFromRequest($request, User::findOrFail($id));
        Audit::log('created', 'Staff', $data['name']);

        return redirect('/app/staff')->with('status', 'Staff member added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $user = User::whereIn('role', ['teacher', 'staff'])->findOrFail($id);
        $data = $this->validated($request, $id);

        if ($id === $request->user()->id && ! $data['is_active']) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        DB::transaction(function () use ($user, $data, $id) {
            $user->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'is_active' => $data['is_active'], 'role' => $data['role']]);
            if (filled($data['username'])) {
                $user->username = $data['username'];
            }
            if (filled($data['email'])) {
                $user->email = $data['email'];
            }
            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }
            $user->save();

            $fields = ['designation' => $data['designation'] ?? null, 'department' => $data['department'] ?? null, 'joined_on' => $data['joined_on'] ?? null, 'address' => $data['address'] ?? null, 'updated_at' => now()];
            if (DB::table('staff_profiles')->where('user_id', $id)->exists()) {
                DB::table('staff_profiles')->where('user_id', $id)->update($fields);
            } else {
                DB::table('staff_profiles')->insert($fields + ['user_id' => $id, 'employee_no' => $this->nextEmployeeNo(), 'created_at' => now()]);
            }
        });
        Audit::log('updated', 'Staff', $user->name);

        return redirect('/app/staff')->with('status', 'Staff member updated.');
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $user = User::whereIn('role', ['teacher', 'staff'])->findOrFail($id);
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        $user->update(['is_active' => ! $user->is_active]);
        Audit::log('updated', 'Staff', $user->name.($user->is_active ? ' activated' : ' deactivated'));

        return back()->with('status', 'Staff member updated.');
    }

    private function validated(Request $request, ?int $id): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', Rule::in(['teacher', 'staff'])],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:8', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'joined_on' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            ...\App\Support\UserFiles::requestRules(),
        ]);
        foreach (['username', 'email', 'phone', 'password', 'designation', 'department', 'joined_on', 'address'] as $optional) {
            $data[$optional] ??= null;
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    private function nextEmployeeNo(): string
    {
        $n = (int) substr((string) DB::table('staff_profiles')->max('employee_no'), 4) + 1;
        do {
            $no = 'EMP-'.str_pad((string) $n++, 4, '0', STR_PAD_LEFT);
        } while (DB::table('staff_profiles')->where('employee_no', $no)->exists());

        return $no;
    }

    private function shared(): array
    {
        return ['navigation' => OfficeMenu::for(request()->user()->role), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        \App\Support\Permissions::office($request);
    }
}
