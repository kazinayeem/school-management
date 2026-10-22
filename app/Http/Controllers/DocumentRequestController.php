<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\CertificateIssuer;
use App\Support\Comms;
use App\Support\OfficeMenu;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Students and parents ask for a transcript or certificate; the office approves, and the document appears for download. */
class DocumentRequestController extends Controller
{
    public const KINDS = [
        'transcript' => 'Academic transcript',
        'transfer_certificate' => 'Transfer certificate',
        'character_certificate' => 'Character certificate',
        'testimonial' => 'Testimonial',
        'other' => 'Other certificate',
    ];

    private const TEMPLATE_NAME = ['transfer_certificate' => 'Transfer Certificate', 'character_certificate' => 'Character Certificate', 'testimonial' => 'Testimonial'];

    // ---- student and parent

    public function index(Request $request): Response
    {
        $user = $request->user();
        $children = $this->children($user);
        $rows = DB::table('document_requests')->whereIn('student_id', $children->pluck('id'))->join('users', 'users.id', '=', 'document_requests.student_id')
            ->orderByDesc('document_requests.id')->limit(100)->get(['document_requests.*', 'users.name as student_name']);

        return Inertia::render('comms/requests', [
            'shell' => MessageController::shell($user),
            'kinds' => self::KINDS,
            'children' => $children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'rows' => $rows->map(fn ($r) => $this->row($r))->all(),
            'office' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'student_id' => ['nullable', 'integer'],
            'purpose' => ['required', 'string', 'max:300'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $children = $this->children($user);
        $student = $user->role === 'parent' ? $children->firstWhere('id', (int) ($data['student_id'] ?? 0)) : $children->first();
        abort_if($student === null, 403);

        if (DB::table('document_requests')->where('student_id', $student->id)->where('kind', $data['kind'])->where('status', 'pending')->exists()) {
            return back()->withErrors(['kind' => 'A request for this document is already waiting for the office.']);
        }

        $id = DB::table('document_requests')->insertGetId([
            'reference' => 'tmp', 'student_id' => $student->id, 'requested_by' => $user->id, 'kind' => $data['kind'], 'purpose' => $data['purpose'], 'needed_by' => $data['needed_by'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('document_requests')->where('id', $id)->update(['reference' => 'REQ-'.date('y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);
        Comms::notify(Comms::adminIds(), 'request', 'New document request', self::KINDS[$data['kind']].' for '.$student->name, '/app/certificates/requests', $user->id);

        return back()->with('status', 'Request sent. You will be notified when the office decides.');
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('document_requests')->where('id', $id)->where('requested_by', $request->user()->id)->where('status', 'pending')->first();
        abort_if($row === null, 404);
        DB::table('document_requests')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);

        return back()->with('status', 'Request cancelled.');
    }

    /** The approved document: a transcript, or the certificate the office issued. */
    public function download(Request $request, int $id)
    {
        $row = DB::table('document_requests')->where('id', $id)->where('status', 'approved')->first();
        abort_if($row === null || ! $this->children($request->user())->contains('id', $row->student_id), 404);
        if ($row->kind === 'transcript') {
            return redirect("/students/{$row->student_id}/transcript.pdf");
        }
        abort_if($row->certificate_id === null, 404);

        return app(PdfController::class)->certificate($request, (int) $row->certificate_id);
    }

    // ---- office

    public function office(Request $request): Response
    {
        Permissions::office($request);
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected', 'cancelled'], true) ? (string) $request->query('status') : 'pending';
        $search = trim((string) $request->query('q', ''));
        $rows = DB::table('document_requests')->join('users', 'users.id', '=', 'document_requests.student_id')->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where('document_requests.status', $status)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('users.name', 'like', "%{$search}%")->orWhere('document_requests.reference', 'like', "%{$search}%")->orWhere('student_profiles.admission_no', 'like', "%{$search}%")))
            ->orderByDesc('document_requests.id')->limit(200)->get(['document_requests.*', 'users.name as student_name', 'student_profiles.class_name', 'student_profiles.section', 'student_profiles.admission_no']);
        $counts = DB::table('document_requests')->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('comms/requests', [
            'shell' => MessageController::shell($request->user()),
            'kinds' => self::KINDS,
            'children' => [],
            'rows' => $rows->map(fn ($r) => $this->row($r) + ['class' => trim(($r->class_name ?? '').' '.($r->section ?? '')), 'admission_no' => $r->admission_no, 'requester' => DB::table('users')->where('id', $r->requested_by)->value('name')])->all(),
            'office' => true,
            'status' => $status,
            'counts' => $counts,
            'q' => $search,
            'templates' => DB::table('certificate_templates')->where('active', true)->orderBy('name')->get(['id', 'name']),
            'defaults' => DB::table('certificate_templates')->whereIn('name', array_values(self::TEMPLATE_NAME))->pluck('id', 'name'),
            'templateByKind' => collect(self::TEMPLATE_NAME)->map(fn ($n) => DB::table('certificate_templates')->where('name', $n)->value('id')),
        ]);
    }

    public function decide(Request $request, int $id): RedirectResponse
    {
        Permissions::office($request);
        $row = DB::table('document_requests')->where('id', $id)->first();
        abort_if($row === null, 404);
        if ($row->status !== 'pending') {
            return back()->with('error', 'This request has already been decided.');
        }
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:500', Rule::requiredIf($request->input('decision') === 'reject')],
            'template_id' => ['nullable', 'integer', 'exists:certificate_templates,id'],
            'date' => ['nullable', 'date'],
        ]);

        $update = ['decision_note' => $data['note'] ?? null, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'updated_at' => now()];
        if ($data['decision'] === 'reject') {
            $update['status'] = 'rejected';
        } else {
            $update['status'] = 'approved';
            if ($row->kind !== 'transcript') {
                $templateId = $data['template_id'] ?? (self::TEMPLATE_NAME[$row->kind] ?? null ? DB::table('certificate_templates')->where('name', self::TEMPLATE_NAME[$row->kind])->value('id') : null);
                $template = $templateId ? DB::table('certificate_templates')->where('id', $templateId)->where('active', true)->first() : null;
                if ($template === null) {
                    return back()->withErrors(['template_id' => 'Choose the certificate template to issue.']);
                }
                $student = CertificateIssuer::student($row->student_id);
                abort_if($student === null, 404);
                $update['certificate_id'] = CertificateIssuer::issue($student, $template, $data['date'] ?? today()->toDateString(), $request->user()->id);
            }
        }
        DB::table('document_requests')->where('id', $id)->update($update);
        Audit::log('updated', 'Document request', "{$row->reference} {$update['status']}");

        $title = self::KINDS[$row->kind] ?? 'Document';
        $verdict = $update['status'] === 'approved' ? 'approved. You can download it now.' : 'declined'.($data['note'] ? ': '.$data['note'] : '.');
        foreach (array_unique([$row->requested_by, $row->student_id]) as $uid) {
            $role = DB::table('users')->where('id', $uid)->value('role');
            Comms::notify($uid, 'request', "{$title} request {$update['status']}", "Your request {$row->reference} was {$verdict}", Comms::base(new User(['role' => $role])).'/requests');
        }

        return back()->with('status', $update['status'] === 'approved' ? 'Request approved.' : 'Request declined.');
    }

    // ---- helpers

    /** @return \Illuminate\Support\Collection<int, object> the students this person may act for */
    private function children(User $user)
    {
        if ($user->role === 'student') {
            return collect([$user]);
        }
        if ($user->role === 'parent') {
            return $user->students()->orderBy('name')->get();
        }

        return $user->role === 'admin' ? DB::table('users')->where('role', 'student')->get() : collect();
    }

    private function row(object $r): array
    {
        return [
            'id' => $r->id, 'reference' => $r->reference, 'student_id' => $r->student_id, 'student' => $r->student_name ?? '', 'kind' => $r->kind, 'purpose' => $r->purpose, 'needed_by' => $r->needed_by,
            'status' => $r->status, 'note' => $r->decision_note, 'created_at' => substr((string) $r->created_at, 0, 10), 'decided_at' => $r->decided_at ? substr((string) $r->decided_at, 0, 10) : null,
            'download' => $r->status === 'approved' && ($r->kind === 'transcript' || $r->certificate_id) ? "/requests/{$r->id}/download" : null,
        ];
    }
}
