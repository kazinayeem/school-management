<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Photos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PhotoController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $this->authorizeFor($request, $user);
        $request->validate(['photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072', 'dimensions:min_width=80,min_height=80']], [
            'photo.max' => 'The picture must be 3 MB or smaller.',
            'photo.image' => 'Choose a JPG, PNG, WebP or GIF picture.',
            'photo.mimes' => 'Choose a JPG, PNG, WebP or GIF picture.',
            'photo.dimensions' => 'The picture must be at least 80 x 80 pixels.',
        ]);

        $old = $user->photo;
        $user->forceFill(['photo' => Photos::store($request->file('photo'), $user->role === 'student' ? 'students' : 'people')])->save();
        Photos::delete($old);
        Audit::log('updated', 'Photo', $user->name);

        return back()->with('status', 'Photo updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeFor($request, $user);
        Photos::delete($user->photo);
        $user->forceFill(['photo' => null])->save();
        Audit::log('deleted', 'Photo', $user->name);

        return back()->with('status', 'Photo removed.');
    }

    /** Admins: anyone. Teachers: themselves and students. Parents: themselves and their own children. Everyone else: themselves. */
    private function authorizeFor(Request $request, User $target): void
    {
        $actor = $request->user();

        $allowed = $actor->role === 'admin'
            || $actor->id === $target->id
            || ($actor->isEmployee() && $target->role === 'student')
            || ($actor->role === 'parent' && DB::table('guardian_student')->where('guardian_id', $actor->id)->where('student_id', $target->id)->exists());

        abort_unless($allowed, 403);
    }
}
