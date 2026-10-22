<?php

namespace App\Http\Controllers;

use App\Support\OfficeMenu;
use App\Support\Permissions;
use App\Support\ProfileData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Every role looks after their own details and password here. Role, username and class are never editable. */
class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'teacher', 'staff'], true), 403);

        return Inertia::render('office/profile', [
            'navigation' => OfficeMenu::for($user->role),
            'roleLabel' => Permissions::label($user->role),
            'profile' => ProfileData::for($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_name' => ['nullable', 'string', 'max:100'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ]);
        foreach (['phone', 'alt_phone', 'address', 'emergency_name', 'emergency_phone', 'blood_group', 'date_of_birth'] as $optional) {
            $data[$optional] = filled($data[$optional] ?? null) ? $data[$optional] : null;
        }
        $user->update($data);

        // Keep the school records that show the same details in step.
        if ($user->role === 'student') {
            DB::table('student_profiles')->where('user_id', $user->id)->update(['address' => $data['address'], 'date_of_birth' => $data['date_of_birth'] ?? DB::raw('date_of_birth'), 'updated_at' => now()]);
        } elseif (in_array($user->role, ['teacher', 'staff'], true)) {
            DB::table('staff_profiles')->where('user_id', $user->id)->update(['address' => $data['address'], 'updated_at' => now()]);
        }

        return back()->with('status', 'Profile saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        $user->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }
}
