<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\UserFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserFileController extends Controller
{
    /** Only an administrator adds or removes papers. Everyone else can only open the ones they are allowed to see. */
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless(in_array($user->role, ['student', 'teacher', 'staff'], true), 404);

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(UserFiles::KINDS))],
            'title' => ['nullable', 'string', 'max:150'],
            'file' => ['required', ...UserFiles::rules()],
        ]);
        UserFiles::save($request->file('file'), $user, $data['kind'], $data['title'] ?? null, $request->user()->id);
        Audit::log('created', 'Document', "{$user->name} · ".UserFiles::KINDS[$data['kind']]);

        return back()->with('status', 'Document uploaded.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $file = DB::table('user_files')->find($id);
        abort_if($file === null, 404);
        UserFiles::deleteFile($file);

        return back()->with('status', 'Document removed.');
    }

    public function show(Request $request, int $id): StreamedResponse
    {
        $file = DB::table('user_files')->find($id);
        abort_if($file === null || ! UserFiles::canView($request->user(), $file), 404);

        $ext = pathinfo($file->path, PATHINFO_EXTENSION);

        return UserFiles::disk()->response($file->path, str($file->title)->slug().'.'.$ext, [
            'Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }
}
