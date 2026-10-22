<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use App\Support\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SmsController extends Controller
{
    private const MAX_RECIPIENTS = 1000;

    public function compose(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/sms-send', [
            ...$this->shared(),
            'templates' => DB::table('sms_templates')->where('active', true)->orderBy('name')->get(['id', 'name', 'body']),
            'classes' => DB::table('academic_classes')->orderBy('sort_order')->pluck('name'),
            'enabled' => SmsGateway::enabled(),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'audience' => ['required', Rule::in(['guardians', 'staff', 'number'])],
            'class_name' => ['nullable', 'required_if:audience,guardians', Rule::exists('academic_classes', 'name')],
            'section' => ['nullable', Rule::in(['A', 'B'])],
            'number' => ['nullable', 'required_if:audience,number', 'regex:/^\+?[0-9]{8,15}$/'],
            'message' => ['required', 'string', 'max:500'],
        ], ['number.regex' => 'Enter a valid phone number.']);

        $recipients = match ($data['audience']) {
            'guardians' => DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')
                ->where('student_profiles.status', 'active')->where('student_profiles.class_name', $data['class_name'])
                ->when($data['section'] ?? null, fn ($q, $s) => $q->where('student_profiles.section', $s))
                ->get(['users.name as student', 'student_profiles.guardian_name as guardian', 'student_profiles.guardian_mobile as phone', 'student_profiles.class_name', 'student_profiles.section'])
                ->map(fn ($r) => ['phone' => $r->phone, 'name' => $r->guardian ?: $r->student, 'vars' => ['{guardian}' => $r->guardian ?: 'Guardian', '{student}' => $r->student, '{class}' => trim($r->class_name.' '.$r->section)]]),
            'staff' => DB::table('users')->where('role', 'staff')->get(['name', 'phone'])->map(fn ($r) => ['phone' => $r->phone, 'name' => $r->name, 'vars' => ['{guardian}' => $r->name, '{student}' => '', '{class}' => '']]),
            'number' => collect([['phone' => $data['number'], 'name' => null, 'vars' => ['{guardian}' => '', '{student}' => '', '{class}' => '']]]),
        };

        $withPhone = $recipients->filter(fn ($r) => filled($r['phone']))->values();
        $skipped = $recipients->count() - $withPhone->count();
        if ($withPhone->isEmpty()) {
            return back()->withErrors(['audience' => 'None of the recipients has a phone number.']);
        }
        if ($withPhone->count() > self::MAX_RECIPIENTS) {
            return back()->withErrors(['audience' => 'Send to at most '.self::MAX_RECIPIENTS.' people at a time.']);
        }

        $ids = [];
        foreach ($withPhone as $r) {
            $ids[] = DB::table('sms_logs')->insertGetId([
                'phone' => $r['phone'], 'recipient' => $r['name'], 'message' => mb_substr(strtr($data['message'], $r['vars']), 0, 500),
                'status' => 'queued', 'sent_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // With a gateway on, the first batch goes out now; the rest wait for "Deliver queued" in the log.
        $delivery = SmsGateway::deliver($ids, SmsGateway::BATCH);
        Audit::log('other', 'SMS', "Queued {$withPhone->count()} messages, {$delivery['sent']} sent");

        return back()->with('sms', ['queued' => $withPhone->count() - $delivery['sent'] - $delivery['failed'], 'skipped' => $skipped, 'sent' => $delivery['sent'], 'failed' => $delivery['failed']]);
    }

    public function log(Request $request): Response
    {
        $this->admin($request);
        $status = (string) $request->query('status', '');

        return Inertia::render('office/sms-log', [
            ...$this->shared(),
            'rows' => DB::table('sms_logs')->when(in_array($status, ['queued', 'sent', 'failed'], true), fn ($q) => $q->where('status', $status))->orderByDesc('id')->limit(300)->get(),
            'counts' => DB::table('sms_logs')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => ['status' => $status],
            'gateway' => ['enabled' => SmsGateway::enabled(), 'provider' => \App\Support\SiteSettings::get('sms_provider', 'manual')],
        ]);
    }

    public function deliverQueued(Request $request): RedirectResponse
    {
        $this->admin($request);
        if (! SmsGateway::enabled()) {
            return back()->with('error', 'Turn on an SMS provider under Settings first.');
        }

        $result = SmsGateway::deliver(null, SmsGateway::BATCH);
        Audit::log('other', 'SMS', "Delivered queued: {$result['sent']} sent, {$result['failed']} failed");

        return back()->with('status', 'Delivery finished.');
    }

    public function mark(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['sent', 'failed'])]]);
        abort_if(DB::table('sms_logs')->where('id', $id)->where('status', 'queued')->update(['status' => $data['status'], 'updated_at' => now()]) === 0, 404);

        return back()->with('status', 'Message updated.');
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
