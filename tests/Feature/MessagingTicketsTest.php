<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Html;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MessagingTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    private function u(string $name): User
    {
        return User::where('username', $name)->firstOrFail();
    }

    private function as(string $name): static
    {
        $this->actingAs($this->u($name));

        return $this;
    }

    private function outsiderParent(): User
    {
        // A parent whose child is not in any of jsmith's sections.
        $child = DB::table('student_profiles')->where('class_name', 'Class 2')->value('user_id');

        return User::findOrFail(DB::table('guardian_student')->where('student_id', $child)->value('guardian_id'));
    }

    // ---------------------------------------------------------------- rich text

    public function test_the_sanitiser_keeps_formatting_and_removes_anything_dangerous(): void
    {
        $good = '<h2>Title</h2><p style="text-align: center"><strong>Bold</strong> <em>it</em> <u>u</u></p><ul><li>one</li></ul><table><tr><th colspan="2">H</th></tr><tr><td>a</td><td>b</td></tr></table><a href="https://example.com">site</a><blockquote>q</blockquote>';
        $clean = Html::clean($good);
        foreach (['<h2>Title</h2>', '<strong>Bold</strong>', '<em>it</em>', '<u>u</u>', 'text-align: center', '<li>one</li>', 'colspan="2"', '<blockquote>q</blockquote>'] as $keep) {
            $this->assertStringContainsString($keep, $clean);
        }
        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);

        $bad = '<script>alert(1)</script><p onclick="x()">hi<img src=x onerror=alert(1)></p><a href="javascript:alert(1)">bad</a><a href=" jav&#x09;ascript:alert(1)">bad2</a><iframe src="//evil"></iframe><p style="background:url(javascript:1)">s</p><svg onload=alert(1)></svg><div><span style="color:red" class="x">ok</span></div><!-- c --><style>*{}</style>';
        $out = Html::clean($bad);
        foreach (['<script', 'onclick', 'onerror', 'javascript', 'iframe', '<svg', '<style', 'background', 'class=', '<img', '<!--'] as $gone) {
            $this->assertStringNotContainsStringIgnoringCase($gone, $out, $gone);
        }
        $this->assertStringContainsString('hi', $out);
        $this->assertStringContainsString('bad', $out, 'text of a blocked link survives');
        $this->assertSame('', Html::clean('   '));
    }

    private function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    // ---------------------------------------------------------------- messages

    public function test_a_teacher_messages_the_admin_and_a_parent_of_their_own_students_only(): void
    {
        $teacher = $this->u('jsmith');
        $admin = $this->u('superadmin');
        $parent = $this->u('parent');
        $stranger = $this->outsiderParent();
        $body = '<p>Hello <strong>there</strong><script>alert(1)</script></p>';

        $this->as('jsmith')->post('/app/messages', ['subject' => 'Lab equipment', 'body' => $body, 'recipients' => [$admin->id]])->assertSessionHasNoErrors();
        $c = DB::table('conversations')->where('subject', 'Lab equipment')->first();
        $this->assertStringNotContainsString('script', DB::table('messages')->where('conversation_id', $c->id)->value('body'));
        $this->assertStringContainsString('<strong>there</strong>', DB::table('messages')->where('conversation_id', $c->id)->value('body'));

        $this->post('/app/messages', ['subject' => 'Test on Sunday', 'body' => '<p>Please revise.</p>', 'recipients' => [$parent->id]])->assertSessionHasNoErrors();
        $this->post('/app/messages', ['subject' => 'Not mine', 'body' => '<p>x</p>', 'recipients' => [$stranger->id]])->assertSessionHasErrors('recipients');
        $this->post('/app/messages', ['subject' => 'Empty', 'body' => '<p></p>', 'recipients' => [$admin->id]])->assertSessionHasErrors('body');
        $this->post('/app/messages', ['subject' => 'Nobody', 'body' => '<p>x</p>', 'recipients' => []])->assertSessionHasErrors('recipients');
        $this->assertSame(0, DB::table('conversations')->where('subject', 'Not mine')->count());

        // The recipient is notified, sees it unread, and opening it marks it read.
        $this->as('superadmin')->get('/app/messages')->assertInertia(fn (Assert $p) => $p->component('comms/messages')->where('counts.unread', fn ($n) => $n >= 1)->where('rows', fn ($r) => collect($r)->contains(fn ($x) => $x['subject'] === 'Lab equipment' && $x['unread'] === 1)));
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $admin->id)->where('url', "/app/messages/{$c->id}")->whereNull('read_at')->count());
        $this->get("/app/messages/{$c->id}")->assertInertia(fn (Assert $p) => $p->component('comms/conversation')->where('messages.0.body', fn ($b) => str_contains($b, 'there')));
        $this->get('/app/messages')->assertInertia(fn (Assert $p) => $p->where('rows', fn ($r) => collect($r)->firstWhere('subject', 'Lab equipment')['unread'] === 0));

        $this->travel(5)->seconds();
        // The admin replies; the teacher gets it; nobody else can read any of it.
        $this->post("/app/messages/{$c->id}/reply", ['body' => '<p>Booked for Tuesday.</p>'])->assertSessionHasNoErrors();
        $this->as('jsmith')->get('/app/messages')->assertInertia(fn (Assert $p) => $p->where('rows', fn ($r) => collect($r)->firstWhere('subject', 'Lab equipment')['unread'] === 1));
        $this->as('parent')->get("/parent/messages/{$c->id}")->assertNotFound();
        $this->as('accountant')->get("/app/messages/{$c->id}")->assertNotFound();
        $this->post("/app/messages/{$c->id}/reply", ['body' => '<p>sneaky</p>'])->assertNotFound();
        $this->as('teacher05')->get("/app/messages/{$c->id}")->assertNotFound();

        // Archive and unarchive per person.
        $this->as('jsmith')->post("/app/messages/{$c->id}/state", ['action' => 'archive'])->assertRedirect('/app/messages');
        $this->get('/app/messages')->assertInertia(fn (Assert $p) => $p->where('rows', fn ($r) => collect($r)->doesntContain('subject', 'Lab equipment')));
        $this->get('/app/messages?box=archived')->assertInertia(fn (Assert $p) => $p->where('rows', fn ($r) => collect($r)->contains('subject', 'Lab equipment')));
        $this->get('/app/messages?box=sent&q=Lab+equipment')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 0)); // archived ones are in Archived only
        $this->assertNotNull($teacher);
    }

    public function test_recipient_search_shows_only_people_the_sender_may_reach(): void
    {
        $names = fn (string $who, string $role, string $q = '') => collect($this->as($who)->getJson(($who === 'student' ? '/student' : ($who === 'parent' ? '/parent' : '/app'))."/messages/recipients?role={$role}&q=".urlencode($q))->assertOk()->json('data'))->pluck('id');

        // A parent finds their child's teachers, the school office and their own child, but not other parents or other children.
        $parent = $this->u('parent');
        $child = $this->u('student');
        $this->assertEmpty($names('parent', 'parent'));
        $this->assertSame([$child->id], $names('parent', 'student')->all());
        $teachers = $names('parent', 'teacher');
        $this->assertTrue($teachers->contains($this->u('jsmith')->id));
        $this->assertFalse($teachers->contains($this->u('teacher01')->id), 'a teacher who does not teach the child');
        $this->assertTrue($names('parent', 'admin')->contains($this->u('superadmin')->id));

        // A student: own teachers, the school office, own parents. Never other students.
        $this->assertEmpty($names('student', 'student'));
        $this->assertSame([$parent->id], $names('student', 'parent')->all());
        $this->assertTrue($names('student', 'teacher')->contains($this->u('jsmith')->id));

        // A teacher: their own students and those students' parents; colleagues and the office.
        $mine = $names('jsmith', 'student', $child->name);
        $this->assertTrue($mine->contains($child->id));
        $this->assertFalse($mine->contains((int) DB::table('student_profiles')->where('class_name', 'Class 2')->value('user_id')));
        $this->assertTrue($names('jsmith', 'parent')->contains($parent->id));
        $this->assertFalse($names('jsmith', 'parent')->contains($this->outsiderParent()->id));
        $this->assertTrue($names('jsmith', 'teacher')->count() > 5);

        // Search by an ID works, and an admin can reach everybody.
        $admission = $child->profile->admission_no;
        $this->assertSame([$child->id], $names('jsmith', 'student', $admission)->all());
        $this->assertGreaterThan(100, count($this->as('superadmin')->getJson('/app/messages/recipients?role=student&q=')->json('data')) + 100);
    }

    public function test_attachments_are_validated_and_only_participants_can_open_them(): void
    {
        $admin = $this->u('superadmin');
        $this->as('jsmith')->post('/app/messages', ['subject' => 'With file', 'body' => '<p>See attached.</p>', 'recipients' => [$admin->id], 'files' => [UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')]])->assertSessionHasNoErrors();
        $a = DB::table('attachments')->first();
        Storage::disk('local')->assertExists($a->path);

        $this->post('/app/messages', ['subject' => 'Bad', 'body' => '<p>x</p>', 'recipients' => [$admin->id], 'files' => [UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')]])->assertSessionHasErrors('files.0');
        $this->post('/app/messages', ['subject' => 'Big', 'body' => '<p>x</p>', 'recipients' => [$admin->id], 'files' => [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->post('/app/messages', ['subject' => 'Html', 'body' => '<p>x</p>', 'recipients' => [$admin->id], 'files' => [$this->realFile('x.pdf', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>')]])->assertSessionHasErrors('files.0');

        $this->get("/attachments/{$a->id}")->assertOk();
        $this->as('superadmin')->get("/attachments/{$a->id}")->assertOk();
        $this->as('parent')->get("/attachments/{$a->id}")->assertNotFound();
        $this->as('teacher05')->get("/attachments/{$a->id}")->assertNotFound();
    }

    // ---------------------------------------------------------------- tickets

    private function ticket(string $by, array $over = []): int
    {
        $base = $by === 'superadmin' ? '/app' : (in_array($by, ['student', 'parent'], true) ? "/{$by}" : '/app');
        $this->as($by)->post("{$base}/tickets", $over + ['title' => 'Something is wrong', 'category' => 'other', 'priority' => 'normal', 'description' => '<p>Details of the problem.</p>'])->assertSessionHasNoErrors();

        return (int) DB::table('tickets')->latest('id')->value('id');
    }

    public function test_a_parent_complaint_about_a_teacher_goes_to_the_admin_and_the_teacher_cannot_see_it(): void
    {
        $child = $this->u('student');
        $teacher = $this->u('jsmith');
        $id = $this->ticket('parent', ['title' => 'Unfair marking', 'category' => 'teacher_complaint', 'priority' => 'high', 'student_id' => $child->id, 'teacher_id' => $teacher->id, 'requester_id' => $this->u('superadmin')->id, 'status' => 'closed']);
        $t = DB::table('tickets')->find($id);

        // Whatever the request claimed, the requester is the signed-in parent and the status starts as submitted.
        $this->assertSame([$this->u('parent')->id, 'submitted', $child->id, $teacher->id], [(int) $t->requester_id, $t->status, (int) $t->student_id, (int) $t->teacher_id]);
        $this->assertMatchesRegularExpression('/^TKT-\d{2}-\d{5}$/', $t->reference);
        $this->assertSame('created', DB::table('ticket_events')->where('ticket_id', $id)->value('type'));

        // The administrator is told; the named teacher is not, and cannot open it.
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->u('superadmin')->id)->where('url', "/app/tickets/{$id}")->count());
        $this->assertSame(0, DB::table('app_notifications')->where('user_id', $teacher->id)->where('url', 'like', "%/tickets/{$id}")->count());
        $this->as('jsmith')->get("/app/tickets/{$id}")->assertNotFound();
        $this->as('nusrat')->get("/student/tickets/{$id}")->assertNotFound();
        $this->as('parent')->get("/parent/tickets/{$id}")->assertOk()->assertInertia(fn (Assert $p) => $p->component('comms/ticket')->where('ticket.teacher.name', $teacher->name)->where('can.manage', false)->where('can.transitions', []));
    }

    public function test_tickets_may_only_name_people_the_requester_is_allowed_to_name(): void
    {
        $other = DB::table('student_profiles')->where('class_name', 'Class 2')->value('user_id');
        $this->as('parent')->post('/parent/tickets', ['title' => 'x', 'category' => 'other', 'priority' => 'normal', 'description' => '<p>x</p>', 'student_id' => $other])->assertForbidden();
        $this->as('student')->post('/student/tickets', ['title' => 'x', 'category' => 'other', 'priority' => 'urgent', 'description' => '<p>x</p>'])->assertSessionHasErrors('priority');
        $notTeaching = $this->u('teacher01');
        $this->as('student')->post('/student/tickets', ['title' => 'x', 'category' => 'teacher_complaint', 'priority' => 'normal', 'description' => '<p>x</p>', 'teacher_id' => $notTeaching->id])->assertForbidden();
        $this->as('jsmith')->post('/app/tickets', ['title' => 'x', 'category' => 'other', 'priority' => 'normal', 'description' => '<p>x</p>', 'student_id' => $other])->assertForbidden();
        $this->as('parent')->post('/parent/tickets', ['title' => 'x', 'category' => 'other', 'priority' => 'normal', 'description' => '<p></p>'])->assertSessionHasErrors('description');
        $this->post('/parent/tickets', ['title' => 'x', 'category' => 'nonsense', 'priority' => 'normal', 'description' => '<p>x</p>'])->assertSessionHasErrors('category');
        $this->post('/parent/tickets', ['title' => 'x', 'category' => 'other', 'priority' => 'normal', 'description' => '<p>x</p>', 'assigned_to' => $this->u('accountant')->id])->assertSessionHasErrors('assigned_to');
        $this->assertSame(2, DB::table('tickets')->count(), 'only the two seeded tickets exist');
    }

    public function test_teacher_raises_an_issue_for_their_student_and_admin_assigns_and_resolves_it(): void
    {
        $child = $this->u('student');
        $admin = $this->u('superadmin');
        $accountant = $this->u('accountant');
        $id = $this->ticket('jsmith', ['title' => 'Repeated absence', 'category' => 'attendance', 'priority' => 'high', 'student_id' => $child->id, 'assigned_to' => $admin->id, 'files' => [UploadedFile::fake()->image('proof.png')]]);
        $this->assertSame($admin->id, (int) DB::table('tickets')->where('id', $id)->value('assigned_to'));
        $this->assertSame(1, DB::table('attachments')->where('attachable_type', 'ticket')->where('attachable_id', $id)->count());

        // Admin assigns to the accountant; both are told, and the assignment is in the history.
        $this->as('superadmin')->post("/app/tickets/{$id}", ['assigned_to' => $accountant->id, 'note' => 'Please follow up with fees.'])->assertSessionHasNoErrors();
        $this->assertSame($accountant->id, (int) DB::table('tickets')->where('id', $id)->value('assigned_to'));
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $accountant->id)->where('url', "/app/tickets/{$id}")->count());
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $id, 'type' => 'assigned', 'to_value' => (string) $accountant->id]);
        // Only an administrator can assign.
        $this->as('jsmith')->post("/app/tickets/{$id}", ['assigned_to' => $this->u('jsmith')->id])->assertForbidden();

        // The assignee works the ticket: valid moves only, a resolution summary is required.
        $this->as('accountant')->get("/app/tickets/{$id}")->assertInertia(fn (Assert $p) => $p->where('can.manage', true)->where('can.assign', false)->where('can.internal', true));
        $this->post("/app/tickets/{$id}", ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->post("/app/tickets/{$id}", ['status' => 'reopened'])->assertStatus(422);
        $this->post("/app/tickets/{$id}", ['status' => 'resolved'])->assertSessionHasErrors('resolution');
        $this->post("/app/tickets/{$id}", ['status' => 'resolved', 'resolution' => '<p>Spoke to the family; attendance is back on track.</p><script>x</script>'])->assertSessionHasNoErrors();
        $t = DB::table('tickets')->find($id);
        $this->assertSame('resolved', $t->status);
        $this->assertNotNull($t->resolved_at);
        $this->assertStringNotContainsString('script', $t->resolution);

        // The teacher who raised it is told at each step and can read the resolution.
        $this->assertGreaterThanOrEqual(2, DB::table('app_notifications')->where('user_id', $this->u('jsmith')->id)->where('url', "/app/tickets/{$id}")->count());
        $this->as('jsmith')->get("/app/tickets/{$id}")->assertInertia(fn (Assert $p) => $p->where('ticket.status', 'resolved')->where('ticket.resolution', fn ($r) => str_contains($r, 'back on track'))->where('can.transitions', fn ($t) => collect($t)->contains('reopened') && collect($t)->contains('closed')));

        // The requester reopens, the assignee cannot skip steps, and the history is complete and in order.
        $this->post("/app/tickets/{$id}", ['status' => 'reopened'])->assertSessionHasNoErrors();
        $this->assertNull(DB::table('tickets')->where('id', $id)->value('resolved_at'));
        $this->as('accountant')->post("/app/tickets/{$id}", ['status' => 'closed'])->assertSessionHasNoErrors();
        $this->post("/app/tickets/{$id}", ['status' => 'in_progress'])->assertStatus(422);
        $this->post("/app/tickets/{$id}/reply", ['body' => '<p>late</p>'])->assertForbidden();
        $this->assertSame(['created', 'assigned', 'assigned', 'status', 'status', 'status', 'status'], DB::table('ticket_events')->where('ticket_id', $id)->orderBy('id')->pluck('type')->all());
        $this->assertSame(['submitted', 'in_progress', 'resolved', 'reopened'], DB::table('ticket_events')->where('ticket_id', $id)->where('type', 'status')->orderBy('id')->pluck('from_value')->map(fn ($v) => $v)->take(4)->all());
    }

    public function test_replies_internal_notes_and_the_requester_answering(): void
    {
        $id = $this->ticket('student', ['title' => 'Cannot see my result', 'category' => 'exams']);
        $admin = $this->u('superadmin');

        // Student reply notifies the admins.
        $this->post("/student/tickets/{$id}/reply", ['body' => '<p>Any update?</p>'])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $admin->id)->where('title', 'like', 'New reply on%')->count());

        // Admin asks for more detail (waiting), adds an internal note with a file, then replies publicly.
        $this->as('superadmin')->post("/app/tickets/{$id}", ['status' => 'waiting'])->assertSessionHasNoErrors();
        $this->post("/app/tickets/{$id}/reply", ['body' => '<p>Check with Mr X before answering.</p>', 'internal' => true, 'files' => [UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')]])->assertSessionHasNoErrors();
        $this->post("/app/tickets/{$id}/reply", ['body' => '<p>Which exam do you mean?</p>'])->assertSessionHasNoErrors();
        $internal = DB::table('ticket_replies')->where('is_internal', true)->first();
        $file = DB::table('attachments')->where('attachable_type', 'reply')->where('attachable_id', $internal->id)->first();

        // The student sees the public reply only: never the note, its attachment, or a notification about it.
        $this->as('student')->get("/student/tickets/{$id}")->assertInertia(fn (Assert $p) => $p->has('replies', 2)->where('replies', fn ($r) => collect($r)->doesntContain('internal', true))->where('can.internal', false));
        $this->get("/attachments/{$file->id}")->assertNotFound();
        $this->post("/student/tickets/{$id}/reply", ['body' => '<p>sneaky</p>', 'internal' => true])->assertForbidden();
        $this->assertSame(0, DB::table('app_notifications')->where('user_id', $this->u('student')->id)->where('body', 'like', '%Check with Mr X%')->count());
        $this->as('superadmin')->get("/attachments/{$file->id}")->assertOk();
        $this->get("/app/tickets/{$id}")->assertInertia(fn (Assert $p) => $p->has('replies', 3));

        // The student answering a "waiting" ticket moves it on by itself.
        $this->as('student')->post("/student/tickets/{$id}/reply", ['body' => '<p>The half yearly exam.</p>'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', DB::table('tickets')->where('id', $id)->value('status'));
        // Replying to a resolved ticket reopens it.
        $this->as('superadmin')->post("/app/tickets/{$id}", ['status' => 'resolved', 'resolution' => '<p>Fixed.</p>'])->assertSessionHasNoErrors();
        $this->as('student')->post("/student/tickets/{$id}/reply", ['body' => '<p>Still wrong.</p>'])->assertSessionHasNoErrors();
        $this->assertSame('reopened', DB::table('tickets')->where('id', $id)->value('status'));
    }

    public function test_ticket_lists_filter_search_and_paginate_for_each_role(): void
    {
        foreach (range(1, 25) as $i) {
            $this->ticket('parent', ['title' => "Parent issue {$i}", 'category' => $i % 2 ? 'fees' : 'transport', 'priority' => $i % 5 === 0 ? 'high' : 'normal']);
        }
        $this->ticket('student', ['title' => 'Student issue', 'category' => 'library']);

        $this->as('superadmin')->get('/app/tickets')->assertInertia(fn (Assert $p) => $p->component('comms/tickets')->has('rows', 20)->where('pagination.total', 28)->where('pagination.totalPages', 2)->where('stats.total', 28)->where('stats.unassigned', 27)->where('admin', true));
        $this->get('/app/tickets?limit=50')->assertInertia(fn (Assert $p) => $p->has('rows', 28));
        $this->get('/app/tickets?category=fees')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 13));
        $this->get('/app/tickets?priority=high')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 6)->where('rows', fn ($r) => collect($r)->every(fn ($x) => in_array($x['priority'], ['high'], true))));
        $this->get('/app/tickets?q=Student+issue')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 1));
        $this->get('/app/tickets?assigned=none&status=open')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 27));
        $this->get('/app/tickets?category=bogus&priority=bogus&sort=evil&dir=sideways')->assertOk();
        $this->get('/app/tickets?page=99')->assertInertia(fn (Assert $p) => $p->where('pagination.page', 2));

        // A parent sees only their own, a student theirs, a teacher none of these.
        $this->as('parent')->get('/parent/tickets')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 26)->where('admin', false)->where('stats.created', 26));
        $this->as('student')->get('/student/tickets')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 1));
        $this->as('jsmith')->get('/app/tickets')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 1)->where('rows.0.title', 'Projector in Room 205 is not working')->where('staffSide', true));
        $this->as('jsmith')->get('/app/tickets?view=all')->assertInertia(fn (Assert $p) => $p->where('pagination.total', 1));
    }

    // ---------------------------------------------------------------- notifications and access

    public function test_notifications_open_the_right_page_and_counts_follow_reading(): void
    {
        $admin = $this->u('superadmin');
        $id = $this->ticket('parent', ['title' => 'Bus is late']);
        $this->as('superadmin')->get('/app')->assertInertia(fn (Assert $p) => $p->where('comms.notifications', fn ($n) => $n >= 2)->where('comms.base', '/app'));
        $n = DB::table('app_notifications')->where('user_id', $admin->id)->where('url', "/app/tickets/{$id}")->first();
        $this->get("/app/notifications/{$n->id}/open")->assertRedirect("/app/tickets/{$id}");
        $this->assertNotNull(DB::table('app_notifications')->find($n->id)->read_at);
        $this->as('jsmith')->get("/app/notifications/{$n->id}/open")->assertNotFound();

        // An unsafe address stored in a notification is never followed off the site.
        $evil = DB::table('app_notifications')->insertGetId(['user_id' => $admin->id, 'kind' => 'x', 'title' => 'x', 'url' => '//evil.example/x', 'created_at' => now()]);
        $this->as('superadmin')->get("/app/notifications/{$evil}/open")->assertRedirect('/app/notifications');

        $this->get('/app/notifications')->assertInertia(fn (Assert $p) => $p->component('comms/notifications')->has('rows'));
        $this->post('/app/notifications/read-all')->assertRedirect();
        $this->assertSame(0, DB::table('app_notifications')->where('user_id', $admin->id)->whereNull('read_at')->count());
        $this->getJson('/notifications/counts')->assertOk()->assertJsonPath('notifications', 0);

        // Opening a conversation clears its notification too.
        $this->as('jsmith')->post('/app/messages', ['subject' => 'Hi', 'body' => '<p>x</p>', 'recipients' => [$admin->id]]);
        $c = DB::table('conversations')->where('subject', 'Hi')->value('id');
        $this->as('superadmin')->get("/app/messages/{$c}");
        $this->assertSame(0, DB::table('app_notifications')->where('user_id', $admin->id)->whereNull('read_at')->count());
    }

    public function test_pages_belong_to_their_own_roles_and_menus_link_to_them(): void
    {
        $this->as('student')->get('/app/messages')->assertForbidden();
        $this->get('/parent/messages')->assertForbidden();
        $this->as('parent')->get('/student/tickets')->assertForbidden();
        $this->as('jsmith')->get('/student/messages')->assertForbidden();
        $this->post('/logout');
        $this->get('/app/messages')->assertRedirect('/login');

        foreach (['superadmin' => '/app', 'jsmith' => '/app', 'accountant' => '/app', 'student' => '/student', 'parent' => '/parent'] as $who => $base) {
            $this->as($who)->get("{$base}/messages")->assertOk();
            $this->get("{$base}/messages/compose")->assertOk()->assertInertia(fn (Assert $p) => $p->component('comms/compose')->has('roles'));
            $this->get("{$base}/tickets")->assertOk();
            $this->get("{$base}/tickets/create")->assertOk();
            $this->get("{$base}/notifications")->assertOk();
        }
        $this->as('jsmith')->get('/app')->assertInertia(fn (Assert $p) => $p->where('navigation.0.items', fn ($items) => collect($items)->pluck('href')->contains('/app/messages') && collect($items)->pluck('href')->contains('/app/tickets')));
        $this->as('student')->get('/student')->assertInertia(fn (Assert $p) => $p->where('menu', fn ($m) => collect($m)->pluck('url')->contains('/student/messages')));
        $this->as('parent')->get('/parent')->assertInertia(fn (Assert $p) => $p->where('menu', fn ($m) => collect($m)->pluck('url')->contains('/parent/tickets')));
    }
}
