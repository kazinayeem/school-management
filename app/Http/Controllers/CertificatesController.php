<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CertificatesController extends Controller
{
    public function issueForm(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/issue-certificate', [
            ...$this->shared(),
            'templates' => DB::table('certificate_templates')->where('active', true)->orderBy('name')->get(['id', 'name', 'body']),
            'recent' => $this->listQuery()->limit(8)->get(),
        ]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:certificate_templates,id'],
            'student' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
        ]);

        $student = DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where(fn ($q) => $q->where('student_profiles.admission_no', $data['student'])->orWhere('users.username', $data['student']))
            ->first(['users.id', 'users.name', 'student_profiles.class_name', 'student_profiles.section', 'student_profiles.roll_no', 'student_profiles.admission_no', 'student_profiles.guardian_name']);
        if ($student === null) {
            return back()->withErrors(['student' => 'No student found with that admission number or username.']);
        }

        $template = DB::table('certificate_templates')->where('id', $data['template_id'])->where('active', true)->first();
        if ($template === null) {
            return back()->withErrors(['template_id' => 'That template is not active.']);
        }

        $body = strtr($template->body, [
            '{student_name}' => $student->name, '{guardian}' => $student->guardian_name ?: '—', '{class}' => trim($student->class_name.' '.$student->section),
            '{roll}' => (string) $student->roll_no, '{admission_no}' => (string) $student->admission_no, '{date}' => date('j F Y', strtotime($data['date'])),
        ]);

        $id = DB::transaction(function () use ($request, $data, $student, $template, $body) {
            $year = date('Y', strtotime($data['date']));
            $seq = (int) DB::table('certificates')->where('number', 'like', "CERT-{$year}-%")->count() + 1;
            do {
                $number = "CERT-{$year}-".str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);
            } while (DB::table('certificates')->where('number', $number)->exists());

            return DB::table('certificates')->insertGetId([
                'number' => $number, 'template_id' => $template->id, 'user_id' => $student->id, 'title' => $template->name, 'body' => $body,
                'issued_on' => $data['date'], 'issued_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        Audit::log('created', 'Certificate', "{$template->name} for {$student->name}");

        return redirect("/app/certificates/{$id}/print");
    }

    public function history(Request $request): Response
    {
        $this->admin($request);
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('office/certificate-history', [
            ...$this->shared(),
            'rows' => $this->listQuery()->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('users.name', 'like', "%{$search}%")->orWhere('certificates.number', 'like', "%{$search}%")->orWhere('certificates.title', 'like', "%{$search}%")))->limit(300)->get(),
            'search' => $search,
        ]);
    }

    public function print(Request $request, int $id): Response
    {
        $this->admin($request);
        $certificate = DB::table('certificates')->join('users', 'users.id', '=', 'certificates.user_id')->where('certificates.id', $id)->first(['certificates.*', 'users.name']);
        abort_if($certificate === null, 404);

        return Inertia::render('office/certificate-print', [
            'certificate' => $certificate,
            'school' => ['name' => SiteSettings::get('school_name', config('app.name')), 'address' => SiteSettings::get('address', '')],
        ]);
    }

    private function listQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('certificates')->join('users', 'users.id', '=', 'certificates.user_id')->orderByDesc('certificates.id')
            ->select('certificates.id', 'certificates.number', 'certificates.title', 'certificates.issued_on', 'users.name');
    }

    private function shared(): array
    {
        return ['navigation' => OfficeMenu::for('admin'), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
