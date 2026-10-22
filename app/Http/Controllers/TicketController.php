<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Comms;
use App\Support\Html;
use App\Support\Listing\Listing;
use App\Support\Photos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Complaints, support requests and administrative requests that need to be tracked until they are resolved. */
class TicketController extends Controller
{
    private const OPEN = ['submitted', 'open', 'acknowledged', 'in_progress', 'waiting', 'reopened'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $admin = $user->role === 'admin';
        $limit = Listing::limit($request);
        $f = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'priority' => (string) $request->query('priority', ''),
            'category' => (string) $request->query('category', ''),
            'assigned' => (string) $request->query('assigned', ''),
            'view' => in_array($request->query('view'), ['all', 'mine', 'assigned'], true) ? (string) $request->query('view') : ($admin ? 'all' : 'mine'),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $base = Comms::ticketsFor($user);
        $query = (clone $base)->join('users as r', 'r.id', '=', 'tickets.requester_id')->leftJoin('users as a', 'a.id', '=', 'tickets.assigned_to')
            ->when($f['view'] === 'mine', fn ($q) => $q->where('tickets.requester_id', $user->id))
            ->when($f['view'] === 'assigned', fn ($q) => $q->where('tickets.assigned_to', $user->id))
            ->when($f['status'] === 'open', fn ($q) => $q->whereIn('tickets.status', self::OPEN), fn ($q) => $q->when(array_key_exists($f['status'], Comms::STATUSES), fn ($q) => $q->where('tickets.status', $f['status'])))
            ->when(array_key_exists($f['priority'], Comms::PRIORITIES), fn ($q) => $q->where('tickets.priority', $f['priority']))
            ->when(array_key_exists($f['category'], Comms::CATEGORIES), fn ($q) => $q->where('tickets.category', $f['category']))
            ->when($admin && $f['assigned'] === 'none', fn ($q) => $q->whereNull('tickets.assigned_to'))
            ->when($admin && ctype_digit($f['assigned']), fn ($q) => $q->where('tickets.assigned_to', (int) $f['assigned']))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from']), fn ($q) => $q->whereDate('tickets.created_at', '>=', $f['from']))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to']), fn ($q) => $q->whereDate('tickets.created_at', '<=', $f['to']))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('tickets.title', 'like', "%{$f['q']}%")->orWhere('tickets.reference', 'like', "%{$f['q']}%")->orWhere('r.name', 'like', "%{$f['q']}%")));

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $limit));
        $page = min(max(1, (int) $request->query('page', 1)), $pages);
        $sort = in_array($request->query('sort'), ['created', 'priority', 'status'], true) ? (string) $request->query('sort') : 'activity';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $query->when($sort === 'created', fn ($q) => $q->orderBy('tickets.created_at', $dir))->when($sort === 'status', fn ($q) => $q->orderBy('tickets.status', $dir))
            ->when($sort === 'priority', fn ($q) => $q->orderByRaw("case tickets.priority when 'urgent' then 4 when 'high' then 3 when 'normal' then 2 else 1 end {$dir}"))
            ->when($sort === 'activity', fn ($q) => $q->orderBy('tickets.last_activity_at', $dir))->orderByDesc('tickets.id');
        $rows = $query->forPage($page, $limit)->get(['tickets.id', 'tickets.reference', 'tickets.title', 'tickets.category', 'tickets.priority', 'tickets.status', 'tickets.created_at', 'tickets.last_activity_at', 'tickets.assigned_to', 'tickets.requester_id', 'r.name as requester', 'a.name as assignee']);

        $open = (clone $base)->whereIn('status', self::OPEN);
        $stats = $admin ? [
            'total' => (clone $base)->count(), 'open' => (clone $open)->count(), 'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
            'urgent' => (clone $open)->whereIn('priority', ['high', 'urgent'])->count(), 'waiting' => (clone $base)->where('status', 'waiting')->count(),
            'resolved' => (clone $base)->whereIn('status', ['resolved', 'closed'])->count(),
            'avg_hours' => (clone $base)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at'])->avg(fn ($t) => \Illuminate\Support\Carbon::parse($t->created_at)->diffInMinutes($t->resolved_at) / 60),
        ] : [
            'created' => DB::table('tickets')->where('requester_id', $user->id)->count(), 'assigned' => DB::table('tickets')->where('assigned_to', $user->id)->whereIn('status', self::OPEN)->count(),
            'open' => DB::table('tickets')->where('requester_id', $user->id)->whereIn('status', self::OPEN)->count(),
            'awaiting_me' => DB::table('tickets')->where('requester_id', $user->id)->where('status', 'waiting')->count(),
            'resolved' => DB::table('tickets')->where('requester_id', $user->id)->whereIn('status', ['resolved', 'closed'])->count(),
            'student_related' => DB::table('tickets')->where('requester_id', $user->id)->whereNotNull('student_id')->count(),
        ];

        return Inertia::render('comms/tickets', [
            'shell' => MessageController::shell($user),
            'rows' => $rows->map(fn ($t) => (array) $t)->all(),
            'filters' => $f + ['limit' => $limit, 'sort' => $sort, 'dir' => $dir],
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => $pages, 'from' => $total ? ($page - 1) * $limit + 1 : 0, 'to' => min($page * $limit, $total)],
            'stats' => $stats,
            'admin' => $admin,
            'staffSide' => Comms::isStaffSide($user),
            'meta' => ['categories' => Comms::CATEGORIES, 'priorities' => Comms::PRIORITIES, 'statuses' => Comms::STATUSES],
            'assignees' => $admin ? DB::table('users')->whereIn('role', ['admin', 'teacher', 'staff'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']) : [],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('comms/ticket-create', [
            'shell' => MessageController::shell($user),
            'meta' => ['categories' => Comms::CATEGORIES, 'priorities' => collect(Comms::PRIORITIES)->only(Comms::isStaffSide($user) ? array_keys(Comms::PRIORITIES) : ['low', 'normal', 'high'])->all()],
            'students' => Comms::ticketStudents($user)->orderBy('users.name')->limit(300)->get()->map(fn ($s) => (array) $s)->all(),
            'teachers' => Comms::ticketTeachers($user)->orderBy('users.name')->limit(300)->get()->map(fn ($s) => (array) $s)->all(),
            'roles' => Comms::roleChoices($user),
            'preset' => ['student_id' => (int) $request->query('student', 0) ?: null, 'category' => (string) $request->query('category', '')],
            'limits' => ['files' => Comms::MAX_FILES, 'kb' => Comms::FILE_KB, 'types' => Comms::FILE_MIMES],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(array_keys(Comms::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Comms::PRIORITIES))],
            'description' => ['required', 'string', 'max:60000'],
            'recipient_role' => ['nullable', Rule::in(array_keys(Comms::ROLES))],
            'assigned_to' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'files' => ['nullable', 'array', 'max:'.Comms::MAX_FILES],
            'files.*' => Comms::fileRules(),
        ]);
        if ($data['priority'] === 'urgent' && ! Comms::isStaffSide($user)) {
            throw ValidationException::withMessages(['priority' => 'Urgent is for the school office. Choose High instead.']);
        }
        $description = Html::clean($data['description']);
        if (Html::text($description) === '' && ! str_contains($description, '<table')) {
            throw ValidationException::withMessages(['description' => 'Describe the issue first.']);
        }
        // Everything named in the request must be something this person is allowed to name.
        if ($data['student_id'] ?? null) {
            abort_unless(Comms::ticketStudents($user)->where('users.id', (int) $data['student_id'])->exists(), 403, 'You can only raise an issue about your own students or children.');
        }
        if ($data['teacher_id'] ?? null) {
            abort_unless(Comms::ticketTeachers($user)->where('users.id', (int) $data['teacher_id'])->exists(), 403, 'You can only name a teacher who teaches you or your child.');
        }
        $assigned = ($data['assigned_to'] ?? null) ? (int) $data['assigned_to'] : null;
        if ($assigned && ! ($user->role === 'admin' ? DB::table('users')->where('id', $assigned)->whereIn('role', ['admin', 'teacher', 'staff'])->where('is_active', true)->exists() : DB::table('users')->where('id', $assigned)->whereIn('role', ['admin', 'teacher', 'staff'])->exists() && Comms::canMessage($user, $assigned))) {
            throw ValidationException::withMessages(['assigned_to' => 'You cannot send a ticket to that person.']);
        }

        $id = DB::transaction(function () use ($user, $data, $description, $assigned, $request) {
            $id = DB::table('tickets')->insertGetId([
                'reference' => Comms::nextReference(), 'title' => $data['title'], 'category' => $data['category'], 'priority' => $data['priority'], 'status' => 'submitted',
                'requester_id' => $user->id, 'assigned_to' => $assigned, 'recipient_role' => $data['recipient_role'] ?? 'admin', 'student_id' => $data['student_id'] ?? null, 'teacher_id' => $data['teacher_id'] ?? null,
                'description' => $description, 'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            Comms::event($id, $user->id, 'created', null, 'submitted');
            $assigned && Comms::event($id, $user->id, 'assigned', null, (string) $assigned);
            Comms::saveFiles($request, 'files', 'ticket', $id, $user);

            return $id;
        });

        $ticket = DB::table('tickets')->find($id);
        $url = fn (int $uid) => Comms::base(User::find($uid) ?? $user).'/tickets/'.$id;
        foreach (array_unique(array_merge(Comms::adminIds(), $assigned ? [$assigned] : [])) as $uid) {
            Comms::notify($uid, 'ticket', ($uid === $assigned ? 'Ticket assigned to you: ' : 'New ticket: ').$ticket->reference, $data['title'], $url($uid), $user->id);
        }

        return redirect(Comms::base($user).'/tickets/'.$id)->with('status', "Ticket {$ticket->reference} submitted.");
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();
        $ticket = $this->find($user, $id);
        $manager = Comms::canManageTicket($user, $ticket);

        $replies = DB::table('ticket_replies')->join('users', 'users.id', '=', 'ticket_replies.user_id')->where('ticket_id', $id)->when(! $manager, fn ($q) => $q->where('is_internal', false))
            ->orderBy('ticket_replies.id')->get(['ticket_replies.id', 'ticket_replies.body', 'ticket_replies.is_internal', 'ticket_replies.created_at', 'users.id as uid', 'users.name', 'users.role', 'users.photo']);
        $events = DB::table('ticket_events')->leftJoin('users', 'users.id', '=', 'ticket_events.user_id')->where('ticket_id', $id)->orderBy('ticket_events.id')->get(['ticket_events.*', 'users.name as who']);
        $names = DB::table('users')->whereIn('id', $events->flatMap(fn ($e) => $e->type === 'assigned' ? [(int) $e->to_value, (int) $e->from_value] : [])->filter()->unique())->pluck('name', 'id');
        $replyFiles = Comms::filesFor('reply', $replies->pluck('id')->all());
        $ticketFiles = Comms::filesFor('ticket', [$id])[$id] ?? [];
        $person = fn (?int $uid) => $uid ? DB::table('users')->where('id', $uid)->first(['id', 'name', 'role', 'photo']) : null;
        $requester = $person($ticket->requester_id);
        $student = $ticket->student_id ? DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('users.id', $ticket->student_id)->first(['users.id', 'users.name', 'student_profiles.class_name', 'student_profiles.section']) : null;

        return Inertia::render('comms/ticket', [
            'shell' => MessageController::shell($user),
            'ticket' => [
                'id' => $ticket->id, 'reference' => $ticket->reference, 'title' => $ticket->title, 'category' => $ticket->category, 'priority' => $ticket->priority, 'status' => $ticket->status, 'description' => $ticket->description, 'resolution' => $ticket->resolution,
                'created_at' => $ticket->created_at, 'last_activity_at' => $ticket->last_activity_at, 'resolved_at' => $ticket->resolved_at, 'escalated' => (bool) $ticket->escalated, 'recipient_role' => $ticket->recipient_role,
                'requester' => $requester ? ['id' => $requester->id, 'name' => $requester->name, 'role' => $requester->role, 'photo' => Photos::url($requester->photo)] : null,
                'assignee' => ($a = $person($ticket->assigned_to)) ? ['id' => $a->id, 'name' => $a->name, 'role' => $a->role] : null,
                'student' => $student ? ['id' => $student->id, 'name' => $student->name, 'class' => trim($student->class_name.' '.$student->section)] : null,
                'teacher' => ($t = $person($ticket->teacher_id)) ? ['id' => $t->id, 'name' => $t->name] : null,
                'files' => $ticketFiles,
            ],
            'replies' => $replies->map(fn ($r) => ['id' => $r->id, 'body' => $r->body, 'internal' => (bool) $r->is_internal, 'at' => $r->created_at, 'mine' => (int) $r->uid === $user->id, 'author' => ['id' => $r->uid, 'name' => $r->name, 'role' => $r->role, 'photo' => Photos::url($r->photo)], 'files' => $replyFiles[$r->id] ?? []])->all(),
            'events' => $events->map(fn ($e) => ['id' => $e->id, 'type' => $e->type, 'from' => $e->type === 'assigned' ? ($names[(int) $e->from_value] ?? null) : $e->from_value, 'to' => $e->type === 'assigned' ? ($names[(int) $e->to_value] ?? null) : $e->to_value, 'note' => $e->note, 'who' => $e->who, 'at' => $e->created_at])->all(),
            'can' => [
                'manage' => $manager, 'assign' => $user->role === 'admin', 'internal' => $manager, 'reply' => $ticket->status !== 'closed',
                'transitions' => $this->transitions($user, $ticket),
                'priority' => $manager,
            ],
            'meta' => ['categories' => Comms::CATEGORIES, 'priorities' => Comms::PRIORITIES, 'statuses' => Comms::STATUSES],
            'assignees' => $user->role === 'admin' ? DB::table('users')->whereIn('role', ['admin', 'teacher', 'staff'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']) : [],
            'limits' => ['files' => Comms::MAX_FILES, 'kb' => Comms::FILE_KB, 'types' => Comms::FILE_MIMES],
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->find($user, $id);
        abort_if($ticket->status === 'closed', 403, 'This ticket is closed. Reopen it to reply.');
        $manager = Comms::canManageTicket($user, $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:60000'], 'internal' => ['boolean'], 'files' => ['nullable', 'array', 'max:'.Comms::MAX_FILES], 'files.*' => Comms::fileRules()]);
        $internal = $request->boolean('internal');
        abort_if($internal && ! $manager, 403, 'Only the people handling the ticket can add internal notes.');
        $body = Html::clean($data['body']);
        if (Html::text($body) === '' && ! str_contains($body, '<table')) {
            throw ValidationException::withMessages(['body' => 'Write a reply first.']);
        }

        DB::transaction(function () use ($user, $ticket, $body, $internal, $request, $manager) {
            $rid = DB::table('ticket_replies')->insertGetId(['ticket_id' => $ticket->id, 'user_id' => $user->id, 'body' => $body, 'is_internal' => $internal, 'created_at' => now(), 'updated_at' => now()]);
            Comms::saveFiles($request, 'files', 'reply', $rid, $user);
            $update = ['last_activity_at' => now(), 'updated_at' => now()];
            // The person who raised it answering gets it moving again.
            if (! $manager && (int) $ticket->requester_id === $user->id && ! $internal) {
                if ($ticket->status === 'waiting') {
                    $update['status'] = 'in_progress';
                    Comms::event($ticket->id, $user->id, 'status', 'waiting', 'in_progress', 'The requester replied.');
                } elseif ($ticket->status === 'resolved') {
                    $update['status'] = 'reopened';
                    $update['resolved_at'] = null;
                    Comms::event($ticket->id, $user->id, 'status', 'resolved', 'reopened', 'The requester replied.');
                }
            }
            DB::table('tickets')->where('id', $ticket->id)->update($update);
        });

        $audience = $internal
            ? array_merge(Comms::adminIds(), array_filter([(int) $ticket->assigned_to]))
            : array_merge(Comms::adminIds(), array_filter([(int) $ticket->assigned_to, (int) $ticket->requester_id]));
        foreach (array_unique($audience) as $uid) {
            Comms::notify($uid, 'ticket', ($internal ? 'Internal note on ' : 'New reply on ').$ticket->reference, "{$user->name}: ".Html::text($body, 120), Comms::base(User::find($uid) ?? $user).'/tickets/'.$ticket->id, $user->id);
        }

        return back()->with('status', $internal ? 'Internal note added.' : 'Reply sent.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $ticket = $this->find($user, $id);
        $manager = Comms::canManageTicket($user, $ticket);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Comms::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Comms::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer'],
            'unassign' => ['boolean'],
            'resolution' => ['nullable', 'string', 'max:60000'],
            'escalate' => ['boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $changes = [];
        $notify = [];

        if ($request->boolean('unassign') || ($data['assigned_to'] ?? null)) {
            abort_unless($user->role === 'admin', 403, 'Only an administrator can assign a ticket.');
            $to = $request->boolean('unassign') ? null : (int) $data['assigned_to'];
            if ($to && ! DB::table('users')->where('id', $to)->whereIn('role', ['admin', 'teacher', 'staff'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'Choose a teacher, staff member or administrator.']);
            }
            if ($to !== ($ticket->assigned_to ? (int) $ticket->assigned_to : null)) {
                $changes['assigned_to'] = $to;
                Comms::event($id, $user->id, 'assigned', $ticket->assigned_to ? (string) $ticket->assigned_to : null, $to ? (string) $to : null, $data['note'] ?? null);
                $to && $notify[] = [$to, 'Ticket assigned to you: '.$ticket->reference, $ticket->title];
                $ticket->assigned_to && $ticket->assigned_to !== $to && $notify[] = [(int) $ticket->assigned_to, 'Ticket reassigned: '.$ticket->reference, 'It is now handled by someone else.'];
            }
        }

        if (($data['priority'] ?? null) && $data['priority'] !== $ticket->priority) {
            abort_unless($manager, 403, 'Only the people handling the ticket can change its priority.');
            $changes['priority'] = $data['priority'];
            Comms::event($id, $user->id, 'priority', $ticket->priority, $data['priority']);
            $notify[] = [(int) $ticket->requester_id, "Priority changed on {$ticket->reference}", 'Now '.Comms::PRIORITIES[$data['priority']]];
        }

        if ($request->boolean('escalate') && ! $ticket->escalated) {
            abort_unless($manager, 403);
            $changes['escalated'] = true;
            $changes['priority'] = 'urgent';
            Comms::event($id, $user->id, 'escalated', $ticket->priority, 'urgent', $data['note'] ?? null);
            foreach (Comms::adminIds() as $a) {
                $notify[] = [$a, "Escalated: {$ticket->reference}", $ticket->title];
            }
        }

        if (($data['status'] ?? null) && $data['status'] !== $ticket->status) {
            $to = $data['status'];
            abort_unless(in_array($to, $this->transitions($user, $ticket), true), 422, 'That status change is not allowed from here.');
            $resolution = array_key_exists('resolution', $data) ? Html::clean($data['resolution']) : '';
            if ($to === 'resolved') {
                if ($manager && Html::text($resolution) === '' && Html::text((string) $ticket->resolution) === '') {
                    throw ValidationException::withMessages(['resolution' => 'Write a short resolution summary for the person who raised this.']);
                }
                $resolution !== '' && $changes['resolution'] = $resolution;
                $changes['resolved_at'] = now();
            }
            if ($to === 'closed') {
                $changes['closed_at'] = now();
            }
            if ($to === 'reopened') {
                $changes['resolved_at'] = null;
                $changes['closed_at'] = null;
            }
            $changes['status'] = $to;
            Comms::event($id, $user->id, 'status', $ticket->status, $to, $data['note'] ?? null);
            $label = Comms::STATUSES[$to];
            $notify[] = [(int) $ticket->requester_id, "{$ticket->reference} is now {$label}", $to === 'resolved' ? Html::text($changes['resolution'] ?? (string) $ticket->resolution, 200) : $ticket->title];
            if ($user->id === (int) $ticket->requester_id) {
                foreach (array_merge(Comms::adminIds(), array_filter([(int) $ticket->assigned_to])) as $uid) {
                    $notify[] = [$uid, "{$ticket->reference} is now {$label}", $ticket->title];
                }
            }
        }

        if ($changes !== []) {
            DB::table('tickets')->where('id', $id)->update($changes + ['last_activity_at' => now(), 'updated_at' => now()]);
            $seen = [];
            foreach ($notify as [$uid, $title, $body]) {
                if ($uid && $uid !== $user->id && ! isset($seen[$uid.$title])) {
                    $seen[$uid.$title] = true;
                    Comms::notify($uid, 'ticket', $title, (string) $body, Comms::base(User::find($uid) ?? $user).'/tickets/'.$id);
                }
            }
        }

        return back()->with('status', $changes === [] ? 'Nothing to change.' : 'Ticket updated.');
    }

    private function find(User $user, int $id): object
    {
        $ticket = DB::table('tickets')->find($id);
        abort_if($ticket === null || ! Comms::canViewTicket($user, $ticket), 404);

        return $ticket;
    }

    /** The statuses this person may move the ticket to right now. */
    private function transitions(User $user, object $ticket): array
    {
        $next = Comms::TRANSITIONS[$ticket->status] ?? [];
        if (Comms::canManageTicket($user, $ticket)) {
            return array_values($next);
        }
        if ((int) $ticket->requester_id === $user->id) {
            $allowed = [];
            in_array('reopened', $next, true) && ($ticket->status === 'resolved' || ($ticket->closed_at && now()->diffInDays($ticket->closed_at) <= 14)) && $allowed[] = 'reopened';
            $ticket->status === 'resolved' && $allowed[] = 'closed';

            return $allowed;
        }

        return [];
    }
}
