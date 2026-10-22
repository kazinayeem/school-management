<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SystemController extends Controller
{
    public function audit(Request $request): Response
    {
        $this->admin($request);
        $search = trim((string) $request->query('search', ''));
        $action = (string) $request->query('action', '');

        $rows = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->when(in_array($action, ['created', 'updated', 'deleted', 'other'], true), fn ($q) => $action === 'other' ? $q->whereNotIn('audit_logs.action', ['created', 'updated', 'deleted']) : $q->where('audit_logs.action', $action))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('audit_logs.subject', 'like', "%{$search}%")->orWhere('audit_logs.detail', 'like', "%{$search}%")->orWhere('users.name', 'like', "%{$search}%")))
            ->orderByDesc('audit_logs.id')->limit(300)
            ->get(['audit_logs.created_at', 'users.name as who', 'audit_logs.action', 'audit_logs.subject', 'audit_logs.detail', 'audit_logs.ip'])
            ->map(fn ($r) => ['when' => substr((string) $r->created_at, 0, 16), 'who' => $r->who ?? 'System', 'action' => ucfirst($r->action), 'subject' => $r->subject, 'detail' => $r->detail, 'ip' => $r->ip])->all();

        return Inertia::render('office/report', [
            ...$this->shared(),
            'title' => 'Audit Log',
            'subtitle' => 'Who changed what, and when.',
            'filters' => [
                ['name' => 'action', 'label' => 'Action', 'type' => 'select', 'options' => [['value' => '', 'label' => 'All actions'], ['value' => 'created', 'label' => 'Created'], ['value' => 'updated', 'label' => 'Updated'], ['value' => 'deleted', 'label' => 'Deleted'], ['value' => 'other', 'label' => 'Other']]],
                ['name' => 'search', 'label' => 'Search', 'type' => 'text'],
            ],
            'values' => ['action' => $action, 'search' => $search],
            'sections' => [[
                'title' => null,
                'columns' => [['key' => 'when', 'label' => 'Time'], ['key' => 'who', 'label' => 'User'], ['key' => 'action', 'label' => 'Action'], ['key' => 'subject', 'label' => 'Item'], ['key' => 'detail', 'label' => 'Detail'], ['key' => 'ip', 'label' => 'IP address']],
                'rows' => $rows,
            ]],
            'summary' => [],
        ]);
    }

    public function roles(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/roles', [
            ...$this->shared(),
            'modules' => collect(Permissions::MODULES)->map(fn ($m, $key) => ['key' => $key, 'label' => $m[0]])->values(),
            'granted' => ['teacher' => Permissions::granted('teacher'), 'staff' => Permissions::granted('staff')],
            'counts' => DB::table('users')->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
        ]);
    }

    public function saveRoles(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'role' => ['required', 'in:teacher,staff'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ]);

        $permissions = Permissions::normalize($data['permissions']);

        DB::transaction(function () use ($data, $permissions) {
            DB::table('role_permissions')->where('role', $data['role'])->delete();
            if ($permissions !== []) {
                DB::table('role_permissions')->insert(array_map(fn ($p) => ['role' => $data['role'], 'permission' => $p], $permissions));
            }
        });
        Permissions::flush();
        Audit::log('updated', 'Roles & Permissions', ucfirst($data['role']).': '.(count($permissions) ?: 'no').' permissions');

        return back()->with('status', 'Permissions saved.');
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
