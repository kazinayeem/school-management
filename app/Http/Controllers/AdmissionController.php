<?php

namespace App\Http\Controllers;

use App\Support\OfficeMenu;
use App\Support\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    private const STATUSES = ['received', 'under_review', 'admitted', 'rejected'];

    public function index(Request $request): Response
    {
        $this->admin($request);
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $rows = DB::table('admission_applications')
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhere('tracking_no', 'like', $like)->orWhere('mobile', 'like', $like);
            }))
            ->latest('id')
            ->limit(200)
            ->get();

        $counts = DB::table('admission_applications')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('office/admissions', [
            ...$this->shared(),
            'rows' => $rows,
            'counts' => $counts,
            'filters' => ['status' => $status, 'search' => $search],
        ]);
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'admitted', 'rejected'])],
            'section' => ['required_if:status,admitted', 'nullable', Rule::in(['A', 'B'])],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $application = DB::table('admission_applications')->where('id', $id)->first();
        abort_if($application === null, 404);

        if ($application->status === 'admitted') {
            return back()->withErrors(['status' => 'This applicant is already admitted.']);
        }

        DB::transaction(function () use ($application, $data, $id) {
            $studentId = null;

            if ($data['status'] === 'admitted') {
                $student = StudentEnrollment::enroll([
                    'name' => trim($application->first_name.' '.$application->last_name),
                    'class_name' => $application->class_name,
                    'section' => $data['section'],
                    'gender' => ucfirst($application->gender),
                    'date_of_birth' => $application->date_of_birth,
                    'guardian_name' => $application->guardian_name,
                    'guardian_mobile' => $application->mobile,
                    'religion' => ucfirst($application->religion),
                    'address' => $application->address,
                ]);
                $studentId = $student->id;
            }

            DB::table('admission_applications')->where('id', $id)->update([
                'status' => $data['status'],
                'admin_note' => $data['admin_note'] ?? null,
                'student_user_id' => $studentId,
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'Application updated.');
    }

    public function settings(Request $request): Response
    {
        $this->admin($request);
        $fields = $this->settingFields();
        $stored = DB::table('site_settings')->pluck('value', 'key');
        $values = [];
        foreach ($fields as $f) {
            $raw = $stored[$f['name']] ?? null;
            $values[$f['name']] = $f['type'] === 'checkbox' ? ($raw === null ? $f['default'] : (bool) $raw) : ($raw ?? $f['default']);
        }

        return Inertia::render('office/website', [
            ...$this->shared(),
            'resource' => 'settings',
            'baseUrl' => '/app/admissions/settings',
            'definition' => ['label' => 'Portal Settings', 'subtitle' => 'Control the public online admission form.', 'fields' => $fields],
            'values' => $values,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->admin($request);
        $fields = $this->settingFields();
        $data = $request->validate(collect($fields)->mapWithKeys(
            fn ($f) => [$f['name'] => $f['type'] === 'checkbox' ? 'boolean' : $f['rules']]
        )->all());

        foreach ($fields as $f) {
            $value = $f['type'] === 'checkbox' ? (int) ($data[$f['name']] ?? 0) : ($data[$f['name']] ?? '');
            DB::table('site_settings')->updateOrInsert(['key' => $f['name']], ['value' => (string) $value]);
        }

        return back()->with('status', 'Portal settings saved.');
    }

    /** @return list<array<string, mixed>> */
    private function settingFields(): array
    {
        return [
            ['name' => 'admission_open', 'label' => 'Accept online applications', 'type' => 'checkbox', 'default' => true],
            ['name' => 'admission_deadline', 'label' => 'Last date to apply', 'type' => 'date', 'rules' => 'nullable|date', 'default' => ''],
            ['name' => 'admission_fee', 'label' => 'Application fee', 'type' => 'number', 'rules' => 'nullable|integer|min:0', 'default' => '0'],
            ['name' => 'admission_instructions', 'label' => 'Instructions for applicants', 'type' => 'textarea', 'rules' => 'nullable|string|max:2000', 'default' => ''],
        ];
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
