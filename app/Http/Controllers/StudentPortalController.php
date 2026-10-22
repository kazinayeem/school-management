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

class StudentPortalController extends Controller
{
    public function show(Request $request, ?string $page = null): Response
    {
        $student = $this->student($request);
        $page = $page ?: 'home';
        $this->known($page);

        return Inertia::render('portals/student', [
            'page' => $page,
            'profile' => PortalData::profile($student),
            'notices' => PortalData::notices(),
            'data' => $this->payload($student, $page),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $student->update($data);

        return back();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $student->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $student->update(['password' => $data['password']]);

        return back();
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $data = $request->validate([
            'leave_type' => ['required', 'in:Sick Leave,Casual Leave'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $days = \Illuminate\Support\Carbon::parse($data['start_date'])->diffInDays(\Illuminate\Support\Carbon::parse($data['end_date'])) + 1;

        DB::table('leave_requests')->insert([
            ...$data,
            'user_id' => $student->id,
            'days' => $days,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back();
    }

    public function submitHomework(Request $request, int $homework): RedirectResponse
    {
        $student = $this->student($request);
        $item = DB::table('homework_items')->where('id', $homework)->where('user_id', $student->id)->first();
        abort_if($item === null || $item->status === 'marked', 404);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        DB::table('homework_items')->where('id', $homework)->update([
            'status' => 'submitted',
            'note' => $data['note'] ?? null,
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        return back();
    }

    private function student(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->role === 'student' && $user->profile !== null, 403);

        return $user;
    }

    private function known(string $page): void
    {
        abort_unless(in_array($page, ['home', 'routine', 'calendar', 'attendance', 'homework', 'results', 'fees', 'library', 'transport', 'documents', 'leave', 'profile'], true), 404);
    }

    private function payload(User $student, string $page): mixed
    {
        $profile = $student->profile;

        return match ($page) {
            'routine' => PortalData::routine($profile),
            'calendar' => PortalData::calendar(),
            'attendance' => PortalData::attendance($student),
            'homework' => PortalData::homework($student),
            'results' => PortalData::results($student),
            'fees' => PortalData::fees($student),
            'library' => PortalData::library($student),
            'transport' => PortalData::bus($student),
            'documents' => PortalData::documents($student),
            'leave' => PortalData::leaves($student),
            default => null,
        };
    }
}
