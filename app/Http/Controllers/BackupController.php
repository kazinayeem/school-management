<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\BackupService;
use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(Request $request, BackupService $backups): Response
    {
        $this->admin($request);

        return Inertia::render('office/backup', [
            'navigation' => OfficeMenu::for(request()->user()->role),
            'roleLabel' => 'Super Admin',
            'supported' => $backups->supported(),
            'backups' => $backups->all(),
        ]);
    }

    public function create(Request $request, BackupService $backups): RedirectResponse
    {
        $this->admin($request);

        try {
            $name = $backups->create();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Audit::log('other', 'Backup', "Created {$name}");

        return back()->with('status', 'Backup created.');
    }

    public function download(Request $request, BackupService $backups, string $name): BinaryFileResponse|RedirectResponse
    {
        $this->admin($request);

        try {
            return response()->download($backups->path($name));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Request $request, BackupService $backups, string $name): RedirectResponse
    {
        $this->admin($request);

        try {
            $backups->delete($name);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Audit::log('deleted', 'Backup', $name);

        return back()->with('status', 'Backup deleted.');
    }

    public function restore(Request $request, BackupService $backups, string $name): RedirectResponse
    {
        $this->admin($request);
        $request->validate(['confirm' => ['required', 'in:RESTORE']], ['confirm.in' => 'Type RESTORE to confirm.']);

        try {
            $backups->restore($name);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Auth::logout();
        $request->session()->invalidate();

        return redirect('/login');
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
