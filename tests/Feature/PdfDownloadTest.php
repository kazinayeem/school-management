<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $u): User
    {
        return User::where('username', $u)->firstOrFail();
    }

    private function assertPdf($response): void
    {
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_every_document_downloads_as_a_real_pdf(): void
    {
        $student = $this->user('student');
        $admin = $this->user('superadmin');
        $cert = DB::table('certificates')->insertGetId(['number' => 'CERT-1', 'user_id' => $student->id, 'title' => 'Certificate of Merit', 'body' => 'Awarded to Tanvir.', 'issued_on' => today()->toDateString(), 'style' => 'classic', 'show_photo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $exam = DB::table('exams')->value('id');

        $this->actingAs($admin);
        $this->assertPdf($this->get("/students/{$student->id}/transcript.pdf"));
        $this->assertPdf($this->get("/students/{$student->id}/file.pdf"));
        $this->assertPdf($this->get("/app/certificates/{$cert}/pdf"));
        $this->assertPdf($this->get('/app/students/id-cards.pdf?class=Class%208&section=A'));
        $this->assertPdf($this->get("/app/exams/admit-cards.pdf?exam={$exam}&class=Class%208&section=A"));
        $this->assertPdf($this->get('/card.pdf'));
        $this->assertPdf($this->postJson('/pdf/table', [
            'title' => 'Due List', 'filters' => 'Class: Class 8', 'summary' => [['label' => 'Students', 'value' => 3]],
            'sections' => [['columns' => [['key' => 'a', 'label' => 'Name'], ['key' => 'b', 'label' => 'Amount', 'money' => true]], 'rows' => [['a' => 'Tanvir', 'b' => 1500]], 'footer' => ['a' => 'Total', 'b' => 1500]]],
        ]));

        // Students and parents download their own transcript, receipt and card.
        $payment = DB::table('fee_payments')->where('user_id', $student->id)->value('receipt_no');
        foreach (['student', 'parent'] as $who) {
            $this->actingAs($this->user($who));
            $this->assertPdf($this->get("/students/{$student->id}/transcript.pdf"));
            $this->get("/students/{$student->id}/file.pdf")->assertForbidden();
            $this->assertPdf($this->get("/receipts/{$payment}/pdf"));
            $this->assertPdf($this->get('/card.pdf'));
        }

        // Teachers get their own payslip and card.
        $teacher = $this->user('jsmith');
        $slip = DB::table('payslips')->where('user_id', $teacher->id)->value('id');
        $this->actingAs($teacher);
        $this->assertPdf($this->get("/app/me/payslips/{$slip}/pdf"));
        $this->assertPdf($this->get('/card.pdf'));
    }

    public function test_pdfs_respect_who_may_see_the_data(): void
    {
        $student = $this->user('student');
        $payment = DB::table('fee_payments')->where('user_id', $student->id)->value('receipt_no');
        $other = $this->user('nusrat');

        $this->actingAs($other);
        $this->get("/students/{$student->id}/transcript.pdf")->assertForbidden();
        $this->get("/receipts/{$payment}/pdf")->assertNotFound();

        // A teacher cannot download a transcript for a student outside their sections.
        $outsider = DB::table('student_profiles')->where('class_name', 'Class 3')->value('user_id');
        $this->actingAs($this->user('jsmith'))->get("/students/{$outsider}/transcript.pdf")->assertForbidden();
        $this->actingAs($this->user('student'))->get('/app/students/id-cards.pdf')->assertForbidden();
        $this->post('/logout');
        $this->get('/card.pdf')->assertRedirect('/login');
        $this->postJson('/pdf/table', [])->assertUnauthorized();
    }

    public function test_on_screen_tables_export_as_csv_excel_pdf_and_print(): void
    {
        $this->actingAs($this->user('superadmin'));
        $table = ['title' => 'Due List', 'sections' => [['columns' => [['key' => 'a', 'label' => 'Name'], ['key' => 'b', 'label' => 'Amount', 'money' => true]], 'rows' => [['a' => '=SUM(A1)', 'b' => 1500], ['a' => 'তানভীর', 'b' => 20]]]]];

        $csv = $this->postJson('/pdf/table', $table + ['format' => 'csv'])->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString("'=SUM(A1)", $body);
        $this->assertStringContainsString('তানভীর', $body);
        $this->assertStringStartsWith('PK', $this->postJson('/pdf/table', $table + ['format' => 'xlsx'])->streamedContent());
        $this->assertStringContainsString('<table', $this->postJson('/pdf/table', $table + ['format' => 'html'])->getContent());
        $this->assertStringStartsWith('%PDF', $this->postJson('/pdf/table', $table)->getContent());
        $this->postJson('/pdf/table', $table + ['format' => 'exe'])->assertStatus(422);
    }

    public function test_every_pdf_and_page_carries_the_maker_credit(): void
    {
        foreach (['table', 'transcript', 'certificate', 'cards', 'admit', 'payslip', 'receipt'] as $view) {
            $this->assertStringContainsString('Made by bornosoft.bd', file_get_contents(resource_path("views/pdf/{$view}.blade.php")).file_get_contents(resource_path('views/pdf/layout.blade.php')), $view);
        }
        $html = view('pdf.table', ['title' => 'T', 'sections' => [], 'school' => \App\Support\Pdf::school()])->render();
        $this->assertStringContainsString('Made by bornosoft.bd', $html);
        $this->assertStringContainsString('bornosoft.bd', file_get_contents(resource_path('js/components/public-shell.tsx')));
    }
}
