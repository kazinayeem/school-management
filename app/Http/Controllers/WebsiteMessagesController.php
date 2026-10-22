<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteMessagesController extends Controller
{
    private const STATUSES = ['new', 'read', 'replied'];

    public function index(Request $request): Response
    {
        $this->admin($request);
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $rows = DB::table('contact_messages')
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('message', 'like', "%{$search}%")))
            ->orderByRaw("case status when 'new' then 0 else 1 end")->orderByDesc('id')->limit(300)->get();

        return Inertia::render('office/website-messages', [
            'navigation' => OfficeMenu::for('admin'),
            'roleLabel' => 'Super Admin',
            'rows' => $rows,
            'counts' => DB::table('contact_messages')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => ['status' => $status, 'search' => $search],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['status' => ['required', Rule::in(self::STATUSES)]]);
        abort_if(DB::table('contact_messages')->where('id', $id)->update(['status' => $data['status'], 'handled_at' => $data['status'] === 'new' ? null : now(), 'updated_at' => now()]) === 0, 404);

        return back()->with('status', 'Message updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        DB::table('contact_messages')->where('id', $id)->delete();
        Audit::log('deleted', 'Website message', "#{$id}");

        return back()->with('status', 'Entry deleted.');
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
