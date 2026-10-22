<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PortalData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ParentPortalController extends Controller
{
    public function show(Request $request, ?string $page = null): Response
    {
        $parent = $this->parent($request);
        $page = $page ?: 'home';
        abort_unless(in_array($page, ['home', 'attendance', 'routine', 'calendar', 'documents', 'leave', 'homework', 'library', 'transport', 'profile'], true), 404);

        $children = $parent->students()->with('profile')->orderBy('name')->get();
        abort_if($children->isEmpty(), 404);

        $selected = $children->firstWhere('id', (int) $request->query('child')) ?? $children->first();

        return Inertia::render('portals/parent', [
            'page' => $page,
            'child' => $selected->id,
            'children' => $children->map(fn (User $child) => [
                'id' => $child->id,
                'name' => $child->name,
                'class' => $child->profile?->class_name,
                'section' => $child->profile?->section,
            ])->values(),
            'profile' => PortalData::profile($selected),
            'account' => [
                'name' => $parent->name,
                'email' => $parent->email,
                'phone' => $parent->phone,
            ],
            'notices' => PortalData::notices(),
            'data' => $this->payload($selected, $page),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $parent = $this->parent($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($parent->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $parent->update($data);

        return back();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $parent = $this->parent($request);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $parent->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $parent->update(['password' => $data['password']]);

        return back();
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $parent = $this->parent($request);
        $data = $request->validate([
            'child' => ['required', 'integer'],
            'leave_type' => ['required', 'in:Sick Leave,Casual Leave'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $child = $parent->students()->where('users.id', $data['child'])->first();
        abort_if($child === null, 403);

        DB::table('leave_requests')->insert([
            'user_id' => $child->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days' => \Illuminate\Support\Carbon::parse($data['start_date'])->diffInDays(\Illuminate\Support\Carbon::parse($data['end_date'])) + 1,
            'reason' => $data['reason'],
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back();
    }

    private function parent(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->role === 'parent', 403);

        return $user;
    }

    private function payload(User $child, string $page): mixed
    {
        return match ($page) {
            'home' => [
                'results' => PortalData::results($child),
                'fees' => PortalData::fees($child),
            ],
            'routine' => $child->profile ? PortalData::routine($child->profile) : ['days' => [], 'periods' => []],
            'calendar' => PortalData::calendar(),
            'attendance' => PortalData::attendance($child),
            'homework' => PortalData::homework($child),
            'library' => PortalData::library($child),
            'transport' => PortalData::bus($child),
            'documents' => PortalData::documents($child),
            'leave' => PortalData::leaves($child),
            default => null,
        };
    }
}
