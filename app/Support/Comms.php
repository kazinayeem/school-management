<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who may write to whom, who may see which ticket, and the notifications that go out. Every controller asks
 * here, so the rules live in one place and a request can never widen them.
 */
class Comms
{
    public const ROLES = ['admin' => 'Administrators', 'teacher' => 'Teachers', 'staff' => 'Office staff', 'student' => 'Students', 'parent' => 'Parents'];

    public const CATEGORIES = [
        'academic' => 'Academic issue', 'teacher_complaint' => 'Teacher complaint', 'student_complaint' => 'Student complaint', 'parent_complaint' => 'Parent complaint',
        'attendance' => 'Attendance', 'exams' => 'Examination and results', 'fees' => 'Fees and payments', 'technical' => 'Technical support',
        'infrastructure' => 'Infrastructure and facilities', 'transport' => 'Transport', 'library' => 'Library', 'administrative' => 'Administrative request', 'other' => 'Other',
    ];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const STATUSES = ['submitted' => 'Submitted', 'open' => 'Open', 'acknowledged' => 'Acknowledged', 'in_progress' => 'In progress', 'waiting' => 'Waiting for response', 'resolved' => 'Resolved', 'closed' => 'Closed', 'reopened' => 'Reopened'];

    /** The only moves a ticket may make, so a status can never jump somewhere that makes no sense. */
    public const TRANSITIONS = [
        'submitted' => ['open', 'acknowledged', 'in_progress', 'waiting', 'resolved', 'closed'],
        'open' => ['acknowledged', 'in_progress', 'waiting', 'resolved', 'closed'],
        'acknowledged' => ['in_progress', 'waiting', 'resolved', 'closed'],
        'in_progress' => ['waiting', 'resolved', 'closed'],
        'waiting' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['closed', 'reopened'],
        'closed' => ['reopened'],
        'reopened' => ['acknowledged', 'in_progress', 'waiting', 'resolved', 'closed'],
    ];

    public const FILE_MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'txt'];
    public const FILE_KB = 5120;
    public const MAX_FILES = 5;

    // ------------------------------------------------------------------ who may message whom

    /** The kinds of people this user may start a conversation with. */
    public static function roleChoices(User $user): array
    {
        $roles = match ($user->role) {
            'admin' => ['admin', 'teacher', 'staff', 'student', 'parent'],
            'teacher' => ['admin', 'teacher', 'staff', 'student', 'parent'],
            'staff' => ['admin', 'teacher', 'staff'],
            'student' => ['admin', 'teacher', 'parent'],
            'parent' => ['admin', 'teacher', 'student'],
            default => ['admin'],
        };

        return array_map(fn ($r) => ['value' => $r, 'label' => self::ROLES[$r]], $roles);
    }

    /** People the sender may write to, as a query on users. Students and parents are limited to the sender's own circle. */
    public static function recipients(User $user, ?string $role = null, string $search = ''): Builder
    {
        $allowed = array_column(self::roleChoices($user), 'value');
        $q = DB::table('users')->where('users.is_active', true)->where('users.id', '!=', $user->id)->whereIn('users.role', $allowed);
        $role && in_array($role, $allowed, true) ? $q->where('users.role', $role) : ($role ? $q->whereRaw('1 = 0') : null);

        // Reach: which students, parents and teachers this person is connected to.
        $reach = fn (string $r) => match (true) {
            $r === 'student' && $user->role === 'teacher' => fn ($w) => $w->whereIn('users.id', self::teacherStudentIds($user)),
            $r === 'parent' && $user->role === 'teacher' => fn ($w) => $w->whereIn('users.id', DB::table('guardian_student')->whereIn('student_id', self::teacherStudentIds($user))->select('guardian_id')),
            $r === 'teacher' && $user->role === 'student' => fn ($w) => $w->whereIn('users.id', self::teachersOf([$user->id])),
            $r === 'teacher' && $user->role === 'parent' => fn ($w) => $w->whereIn('users.id', self::teachersOf(self::childIds($user))),
            $r === 'parent' && $user->role === 'student' => fn ($w) => $w->whereIn('users.id', DB::table('guardian_student')->where('student_id', $user->id)->select('guardian_id')),
            $r === 'student' && $user->role === 'parent' => fn ($w) => $w->whereIn('users.id', self::childIds($user)),
            default => null,
        };
        $q->where(function ($w) use ($allowed, $role, $reach) {
            foreach ($role ? [$role] : $allowed as $r) {
                $w->orWhere(function ($inner) use ($r, $reach) {
                    $inner->where('users.role', $r);
                    ($scope = $reach($r)) && $scope($inner);
                });
            }
        });

        if ($search !== '') {
            $q->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')
                ->where(fn ($w) => $w->where('users.name', 'like', "%{$search}%")->orWhere('users.email', 'like', "%{$search}%")->orWhere('users.username', 'like', "%{$search}%")
                    ->orWhere('student_profiles.admission_no', 'like', "%{$search}%")->orWhere('staff_profiles.employee_no', 'like', "%{$search}%"));
        }

        return $q->select('users.id', 'users.name', 'users.role', 'users.photo');
    }

    public static function canMessage(User $from, int $toId): bool
    {
        return self::recipients($from)->where('users.id', $toId)->exists();
    }

    /** @return list<int> */
    public static function teacherStudentIds(User $teacher): array
    {
        $q = DB::table('student_profiles')->where('status', 'active');
        TeacherScope::restrict($q, $teacher);

        return $q->pluck('user_id')->all();
    }

    /** @return list<int> */
    public static function childIds(User $parent): array
    {
        return DB::table('guardian_student')->where('guardian_id', $parent->id)->pluck('student_id')->all();
    }

    /** Teachers who teach any of these students' sections. */
    public static function teachersOf(array $studentIds): Builder
    {
        return DB::table('teaching_assignments')->join('student_profiles', function ($j) {
            $j->on('student_profiles.class_name', '=', 'teaching_assignments.class_name')->where(fn ($w) => $w->whereNull('teaching_assignments.section')->orWhereColumn('teaching_assignments.section', 'student_profiles.section'));
        })->whereIn('student_profiles.user_id', $studentIds ?: [0])->select('teaching_assignments.user_id');
    }

    /** Students a ticket may be about: the student themself, a parent's children, a teacher's students, or anyone for an administrator. */
    public static function ticketStudents(User $user): Builder
    {
        $q = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('users.role', 'student')->where('student_profiles.status', 'active');
        match ($user->role) {
            'student' => $q->where('users.id', $user->id),
            'parent' => $q->whereIn('users.id', self::childIds($user) ?: [0]),
            'teacher' => $q->whereIn('users.id', self::teacherStudentIds($user) ?: [0]),
            'admin' => null,
            default => $q->whereRaw('1 = 0'),
        };

        return $q->select('users.id', 'users.name', 'student_profiles.class_name', 'student_profiles.section', 'student_profiles.admission_no');
    }

    /** Teachers a complaint may name. */
    public static function ticketTeachers(User $user): Builder
    {
        $q = DB::table('users')->where('role', 'teacher')->where('is_active', true)->where('users.id', '!=', $user->id);
        match ($user->role) {
            'student' => $q->whereIn('users.id', self::teachersOf([$user->id])),
            'parent' => $q->whereIn('users.id', self::teachersOf(self::childIds($user))),
            default => null,
        };

        return $q->select('users.id', 'users.name');
    }

    // ------------------------------------------------------------------ tickets

    public static function isStaffSide(User $user): bool
    {
        return in_array($user->role, ['admin', 'teacher', 'staff'], true);
    }

    /** May this person open the ticket? The requester, whoever it is assigned to, and any administrator. The teacher a complaint names does not get access. */
    public static function canViewTicket(User $user, object $ticket): bool
    {
        return $user->role === 'admin' || (int) $ticket->requester_id === $user->id || (int) $ticket->assigned_to === $user->id;
    }

    public static function canManageTicket(User $user, object $ticket): bool
    {
        return $user->role === 'admin' || ((int) $ticket->assigned_to === $user->id && self::isStaffSide($user));
    }

    /** Tickets the person may list. */
    public static function ticketsFor(User $user): Builder
    {
        $q = DB::table('tickets');
        if ($user->role !== 'admin') {
            $q->where(fn ($w) => $w->where('tickets.requester_id', $user->id)->orWhere('tickets.assigned_to', $user->id));
        }

        return $q;
    }

    public static function nextReference(): string
    {
        $year = now()->format('y');
        $n = (int) DB::table('tickets')->where('reference', 'like', "TKT-{$year}-%")->count() + 1;
        do {
            $ref = sprintf('TKT-%s-%05d', $year, $n++);
        } while (DB::table('tickets')->where('reference', $ref)->exists());

        return $ref;
    }

    public static function event(int $ticketId, ?int $userId, string $type, ?string $from = null, ?string $to = null, ?string $note = null): void
    {
        DB::table('ticket_events')->insert(['ticket_id' => $ticketId, 'user_id' => $userId, 'type' => $type, 'from_value' => $from, 'to_value' => $to, 'note' => $note, 'created_at' => now()]);
        Audit::log('other', 'Ticket', "#{$ticketId} {$type}".($to ? " → {$to}" : ''));
    }

    // ------------------------------------------------------------------ notifications

    /** @param  list<int>|int  $userIds */
    public static function notify(array|int $userIds, string $kind, string $title, string $body, string $url, ?int $exceptUserId = null): void
    {
        $rows = [];
        foreach (array_unique((array) $userIds) as $id) {
            if ($id && $id !== $exceptUserId) {
                $rows[] = ['user_id' => $id, 'kind' => $kind, 'title' => mb_substr($title, 0, 200), 'body' => mb_substr($body, 0, 300), 'url' => $url, 'created_at' => now()];
            }
        }
        $rows && DB::table('app_notifications')->insert($rows);
    }

    /** Where a link for this role leads: the portals have their own prefix. */
    public static function base(User $user): string
    {
        return match ($user->role) {
            'student' => '/student',
            'parent' => '/parent',
            default => '/app',
        };
    }

    public static function adminIds(): array
    {
        return DB::table('users')->where('role', 'admin')->where('is_active', true)->pluck('id')->all();
    }

    // ------------------------------------------------------------------ attachments

    public static function fileRules(): array
    {
        return ['file', 'mimes:'.implode(',', self::FILE_MIMES), 'max:'.self::FILE_KB, function ($attribute, $value, $fail) {
            // The extension is easy to fake; refuse anything whose content is really a web page or script.
            $mime = $value instanceof UploadedFile ? (string) $value->getMimeType() : '';
            if (in_array($mime, ['text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/javascript', 'application/javascript', 'text/x-php', 'application/x-httpd-php'], true)) {
                $fail('This file type is not allowed.');
            }
        }];
    }

    /** @return list<int> ids of the attachments saved */
    public static function saveFiles(Request $request, string $field, string $type, int $id, User $by): array
    {
        $ids = [];
        foreach (array_slice((array) $request->file($field, []), 0, self::MAX_FILES) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $path = $file->storeAs("attachments/{$type}/{$id}", Str::uuid().'.'.$ext, 'local');
            $ids[] = DB::table('attachments')->insertGetId([
                'attachable_type' => $type, 'attachable_id' => $id, 'uploaded_by' => $by->id, 'name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'path' => $path, 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    /** @return array<int, list<array<string, mixed>>> attachments grouped by the id they belong to */
    public static function filesFor(string $type, array $ids): array
    {
        return DB::table('attachments')->where('attachable_type', $type)->whereIn('attachable_id', $ids ?: [0])->orderBy('id')->get()
            ->groupBy('attachable_id')->map(fn ($g) => $g->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'size' => (int) $a->size, 'mime' => $a->mime, 'url' => "/attachments/{$a->id}"])->all())->all();
    }

    /** May this person open the file? It follows the message or ticket reply it was attached to. */
    public static function canOpenAttachment(User $user, object $a): bool
    {
        if ($a->attachable_type === 'message') {
            $conversationId = DB::table('messages')->where('id', $a->attachable_id)->value('conversation_id');

            return $conversationId && DB::table('conversation_participants')->where('conversation_id', $conversationId)->where('user_id', $user->id)->exists();
        }
        $ticket = null;
        $internal = false;
        if ($a->attachable_type === 'ticket') {
            $ticket = DB::table('tickets')->find($a->attachable_id);
        } elseif ($a->attachable_type === 'reply') {
            $reply = DB::table('ticket_replies')->find($a->attachable_id);
            $ticket = $reply ? DB::table('tickets')->find($reply->ticket_id) : null;
            $internal = (bool) ($reply->is_internal ?? false);
        }
        if ($ticket === null || ! self::canViewTicket($user, $ticket)) {
            return false;
        }

        return ! $internal || self::canManageTicket($user, $ticket) || $user->role === 'admin';
    }
}
