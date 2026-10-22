<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Comms;
use App\Support\Html;
use App\Support\Listing\Listing;
use App\Support\OfficeMenu;
use App\Support\Permissions;
use App\Support\PortalData;
use App\Support\Photos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Private conversations between people the school lets talk to each other. Only the people in a conversation can open it. */
class MessageController extends Controller
{
    public static function shell(User $user): array
    {
        $office = in_array($user->role, ['admin', 'teacher', 'staff'], true);

        return $office
            ? ['kind' => 'office', 'navigation' => OfficeMenu::for($user->role), 'roleLabel' => Permissions::label($user->role), 'base' => '/app']
            : ['kind' => 'portal', 'caption' => $user->role === 'parent' ? 'Parent portal' : 'Student portal', 'roleLabel' => $user->role === 'parent' ? 'Parent' : 'Student', 'notices' => PortalData::notices(), 'base' => Comms::base($user)];
    }

    /** Unread conversations for the header badge. */
    public static function unreadCount(User $user): int
    {
        return (int) DB::table('conversation_participants as cp')->where('cp.user_id', $user->id)->whereNull('cp.archived_at')
            ->where(fn ($w) => $w->where('cp.marked_unread', true)->orWhereExists(fn ($q) => $q->select(DB::raw(1))->from('messages as m')->whereColumn('m.conversation_id', 'cp.conversation_id')->where('m.sender_id', '!=', $user->id)
                ->where(fn ($x) => $x->whereNull('cp.last_read_at')->orWhereColumn('m.created_at', '>', 'cp.last_read_at'))))->count();
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $box = in_array($request->query('box'), ['inbox', 'sent', 'archived'], true) ? (string) $request->query('box') : 'inbox';
        $search = trim((string) $request->query('q', ''));
        $limit = Listing::limit($request);

        $query = DB::table('conversation_participants as cp')->join('conversations as c', 'c.id', '=', 'cp.conversation_id')->where('cp.user_id', $user->id)
            ->when($box === 'archived', fn ($q) => $q->whereNotNull('cp.archived_at'), fn ($q) => $q->whereNull('cp.archived_at'))
            ->when($box === 'sent', fn ($q) => $q->where('c.created_by', $user->id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('c.subject', 'like', "%{$search}%")
                ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('messages as m')->whereColumn('m.conversation_id', 'c.id')->where('m.body_text', 'like', "%{$search}%"))
                ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('conversation_participants as p2')->join('users as u2', 'u2.id', '=', 'p2.user_id')->whereColumn('p2.conversation_id', 'c.id')->where('u2.name', 'like', "%{$search}%"))));

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $limit));
        $page = min(max(1, (int) $request->query('page', 1)), $pages);
        $rows = $query->orderByDesc('c.last_message_at')->orderByDesc('c.id')->forPage($page, $limit)
            ->selectRaw('c.id, c.subject, c.created_by, c.last_message_at, cp.last_read_at, cp.marked_unread, cp.archived_at,
                (select count(*) from messages m where m.conversation_id = c.id and m.sender_id != ? and (cp.last_read_at is null or m.created_at > cp.last_read_at)) as unread', [$user->id])->get();

        $ids = $rows->pluck('id');
        $people = DB::table('conversation_participants as p')->join('users as u', 'u.id', '=', 'p.user_id')->whereIn('p.conversation_id', $ids)->orderBy('u.name')->get(['p.conversation_id', 'u.id', 'u.name', 'u.role', 'u.photo'])->groupBy('conversation_id');
        $last = DB::table('messages')->whereIn('id', DB::table('messages')->whereIn('conversation_id', $ids)->groupBy('conversation_id')->selectRaw('max(id)'))->get()->keyBy('conversation_id');

        return Inertia::render('comms/messages', [
            'shell' => self::shell($user),
            'box' => $box,
            'rows' => $rows->map(fn ($r) => [
                'id' => $r->id, 'subject' => $r->subject, 'unread' => $r->marked_unread ? max(1, (int) $r->unread) : (int) $r->unread, 'at' => $r->last_message_at, 'archived' => (bool) $r->archived_at, 'mine' => (int) $r->created_by === $user->id,
                'people' => collect($people[$r->id] ?? [])->where('id', '!=', $user->id)->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'role' => $p->role, 'photo' => Photos::url($p->photo)])->values()->all(),
                'preview' => $last[$r->id]->body_text ?? '', 'last_sender' => (int) ($last[$r->id]->sender_id ?? 0) === $user->id ? 'me' : 'them',
            ])->all(),
            'filters' => ['box' => $box, 'q' => $search, 'limit' => $limit],
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'totalPages' => $pages, 'from' => $total ? ($page - 1) * $limit + 1 : 0, 'to' => min($page * $limit, $total)],
            'counts' => ['unread' => self::unreadCount($user)],
        ]);
    }

    public function compose(Request $request): Response
    {
        $user = $request->user();
        $to = (int) $request->query('to', 0);
        $preset = $to && Comms::canMessage($user, $to) ? DB::table('users')->where('id', $to)->first(['id', 'name', 'role']) : null;

        return Inertia::render('comms/compose', [
            'shell' => self::shell($user),
            'roles' => Comms::roleChoices($user),
            'preset' => $preset ? ['id' => $preset->id, 'name' => $preset->name, 'role' => $preset->role] : null,
            'subject' => (string) $request->query('subject', ''),
            'limits' => ['files' => Comms::MAX_FILES, 'kb' => Comms::FILE_KB, 'types' => Comms::FILE_MIMES],
        ]);
    }

    /** Search for people to write to, limited to who this person may reach. */
    public function recipients(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = Comms::recipients($user, (string) $request->query('role', '') ?: null, trim((string) $request->query('q', '')))->orderBy('users.name')->limit(15)->get();
        $extra = DB::table('student_profiles')->whereIn('user_id', $rows->pluck('id'))->get(['user_id', 'class_name', 'section', 'admission_no'])->keyBy('user_id');

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id' => $r->id, 'name' => $r->name, 'role' => $r->role, 'photo' => Photos::url($r->photo),
            'detail' => isset($extra[$r->id]) ? "{$extra[$r->id]->class_name} {$extra[$r->id]->section} · {$extra[$r->id]->admission_no}" : (Comms::ROLES[$r->role] ?? $r->role),
        ])->values()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:60000'],
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['integer', 'distinct'],
            'files' => ['nullable', 'array', 'max:'.Comms::MAX_FILES],
            'files.*' => Comms::fileRules(),
        ]);
        $body = Html::clean($data['body']);
        if (Html::text($body) === '' && ! str_contains($body, '<table')) {
            throw ValidationException::withMessages(['body' => 'Write a message first.']);
        }
        foreach ($data['recipients'] as $id) {
            if (! Comms::canMessage($user, (int) $id)) {
                throw ValidationException::withMessages(['recipients' => 'You cannot send a message to one of the people you chose.']);
            }
        }

        $conversation = DB::transaction(function () use ($user, $data, $body, $request) {
            $id = DB::table('conversations')->insertGetId(['subject' => $data['subject'], 'created_by' => $user->id, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('conversation_participants')->insert(collect($data['recipients'])->push($user->id)->unique()->map(fn ($uid) => ['conversation_id' => $id, 'user_id' => $uid, 'last_read_at' => $uid === $user->id ? now() : null])->all());
            $this->addMessage($id, $user, $body, $request);

            return $id;
        });

        foreach (DB::table('users')->whereIn('id', $data['recipients'])->get(['id', 'role']) as $r) {
            Comms::notify($r->id, 'message', "New message from {$user->name}", $data['subject'], Comms::base(new User(['role' => $r->role])).'/messages/'.$conversation);
        }

        return redirect(Comms::base($user).'/messages/'.$conversation)->with('status', 'Message sent.');
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();
        $mine = $this->participation($user, $id);
        $conversation = DB::table('conversations')->find($id);

        $limit = 30;
        $before = (int) $request->query('before', 0);
        $messages = DB::table('messages')->join('users', 'users.id', '=', 'messages.sender_id')->where('messages.conversation_id', $id)->when($before > 0, fn ($q) => $q->where('messages.id', '<', $before))
            ->orderByDesc('messages.id')->limit($limit + 1)->get(['messages.id', 'messages.body', 'messages.created_at', 'users.id as sender_id', 'users.name', 'users.role', 'users.photo']);
        $more = $messages->count() > $limit;
        $messages = $messages->take($limit)->reverse()->values();
        $files = Comms::filesFor('message', $messages->pluck('id')->all());

        DB::table('conversation_participants')->where('conversation_id', $id)->where('user_id', $user->id)->update(['last_read_at' => now(), 'marked_unread' => false]);
        DB::table('app_notifications')->where('user_id', $user->id)->whereNull('read_at')->where('url', 'like', '%/messages/'.$id)->update(['read_at' => now()]);

        return Inertia::render('comms/conversation', [
            'shell' => self::shell($user),
            'conversation' => ['id' => $id, 'subject' => $conversation->subject, 'archived' => (bool) $mine->archived_at, 'created_by' => (int) $conversation->created_by],
            'people' => DB::table('conversation_participants as p')->join('users as u', 'u.id', '=', 'p.user_id')->where('p.conversation_id', $id)->orderBy('u.name')->get(['u.id', 'u.name', 'u.role', 'u.photo'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'role' => $p->role, 'photo' => Photos::url($p->photo), 'me' => $p->id === $user->id])->all(),
            'messages' => $messages->map(fn ($m) => ['id' => $m->id, 'body' => $m->body, 'at' => $m->created_at, 'mine' => (int) $m->sender_id === $user->id, 'sender' => ['id' => $m->sender_id, 'name' => $m->name, 'role' => $m->role, 'photo' => Photos::url($m->photo)], 'files' => $files[$m->id] ?? []])->all(),
            'more' => $more ? $messages->first()->id : null,
            'limits' => ['files' => Comms::MAX_FILES, 'kb' => Comms::FILE_KB, 'types' => Comms::FILE_MIMES],
        ]);
    }

    /** Earlier messages in a long conversation, newest first batch before the given message. */
    public function older(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->participation($user, $id);
        $rows = DB::table('messages')->join('users', 'users.id', '=', 'messages.sender_id')->where('messages.conversation_id', $id)->where('messages.id', '<', (int) $request->query('before', 0))
            ->orderByDesc('messages.id')->limit(31)->get(['messages.id', 'messages.body', 'messages.created_at', 'users.id as sender_id', 'users.name', 'users.role', 'users.photo']);
        $more = $rows->count() > 30;
        $rows = $rows->take(30)->reverse()->values();
        $files = Comms::filesFor('message', $rows->pluck('id')->all());

        return response()->json(['messages' => $rows->map(fn ($m) => ['id' => $m->id, 'body' => $m->body, 'at' => $m->created_at, 'mine' => (int) $m->sender_id === $user->id, 'sender' => ['id' => $m->sender_id, 'name' => $m->name, 'role' => $m->role, 'photo' => Photos::url($m->photo)], 'files' => $files[$m->id] ?? []]), 'more' => $more ? $rows->first()->id : null]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $this->participation($user, $id);
        $data = $request->validate(['body' => ['required', 'string', 'max:60000'], 'files' => ['nullable', 'array', 'max:'.Comms::MAX_FILES], 'files.*' => Comms::fileRules()]);
        $body = Html::clean($data['body']);
        if (Html::text($body) === '' && ! str_contains($body, '<table')) {
            throw ValidationException::withMessages(['body' => 'Write a message first.']);
        }
        DB::transaction(function () use ($id, $user, $body, $request) {
            $this->addMessage($id, $user, $body, $request);
            // A reply brings the conversation back to everyone else's inbox.
            DB::table('conversation_participants')->where('conversation_id', $id)->where('user_id', '!=', $user->id)->update(['archived_at' => null]);
        });

        $subject = (string) DB::table('conversations')->where('id', $id)->value('subject');
        foreach (DB::table('conversation_participants as p')->join('users as u', 'u.id', '=', 'p.user_id')->where('p.conversation_id', $id)->where('p.user_id', '!=', $user->id)->get(['u.id', 'u.role']) as $p) {
            Comms::notify($p->id, 'message', "New reply from {$user->name}", $subject, Comms::base(new User(['role' => $p->role])).'/messages/'.$id);
        }

        return back()->with('status', 'Reply sent.');
    }

    public function state(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $this->participation($user, $id);
        $action = $request->validate(['action' => ['required', 'in:archive,unarchive,unread,read']])['action'];
        $update = match ($action) {
            'archive' => ['archived_at' => now()],
            'unarchive' => ['archived_at' => null],
            'unread' => ['marked_unread' => true],
            'read' => ['marked_unread' => false, 'last_read_at' => now()],
        };
        DB::table('conversation_participants')->where('conversation_id', $id)->where('user_id', $user->id)->update($update);

        return $action === 'unread' || $action === 'archive' ? redirect(Comms::base($user).'/messages') : back();
    }

    private function participation(User $user, int $conversationId): object
    {
        $row = DB::table('conversation_participants')->where('conversation_id', $conversationId)->where('user_id', $user->id)->first();
        abort_if($row === null, 404); // not in the conversation: it does not exist as far as they can tell

        return $row;
    }

    private function addMessage(int $conversationId, User $user, string $body, Request $request): int
    {
        $id = DB::table('messages')->insertGetId(['conversation_id' => $conversationId, 'sender_id' => $user->id, 'body' => $body, 'body_text' => Html::text($body), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('conversations')->where('id', $conversationId)->update(['last_message_at' => now(), 'updated_at' => now()]);
        DB::table('conversation_participants')->where('conversation_id', $conversationId)->where('user_id', $user->id)->update(['last_read_at' => now(), 'marked_unread' => false]);
        Comms::saveFiles($request, 'files', 'message', $id, $user);

        return $id;
    }
}
