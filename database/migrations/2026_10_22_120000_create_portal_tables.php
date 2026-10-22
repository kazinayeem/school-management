<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('class_name');
            $table->string('section')->nullable();
            $table->string('roll_no')->nullable();
            $table->string('session')->default('2026');
            $table->unsignedInteger('attendance_present')->default(0);
            $table->unsignedInteger('attendance_working')->default(0);
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('exam_name')->nullable();
            $table->unsignedSmallInteger('class_position')->nullable();
            $table->unsignedSmallInteger('section_position')->nullable();
            $table->string('promotion_status')->nullable();
            $table->unsignedInteger('dues_total')->default(0);
            $table->unsignedInteger('dues_count')->default(0);
            $table->date('dues_due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('class_periods', function (Blueprint $table) {
            $table->id();
            $table->string('class_name');
            $table->string('section');
            $table->unsignedTinyInteger('day');
            $table->unsignedTinyInteger('sort');
            $table->string('label');
            $table->string('time_range');
            $table->boolean('is_break')->default(false);
            $table->string('subject')->nullable();
            $table->string('teacher')->nullable();
            $table->string('room')->nullable();
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status');
            $table->unique(['user_id', 'date']);
        });

        Schema::create('homework_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('subject');
            $table->string('teacher')->nullable();
            $table->date('due_on');
            $table->string('status')->default('todo');
            $table->text('instructions')->nullable();
            $table->text('note')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exam');
            $table->string('subject');
            $table->unsignedSmallInteger('marks');
            $table->unsignedSmallInteger('full_marks')->default(100);
            $table->string('letter_grade');
            $table->boolean('failed')->default(false);
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_no');
            $table->string('label');
            $table->date('due_on')->nullable();
            $table->unsignedInteger('payable');
            $table->unsignedInteger('paid')->default(0);
            $table->string('status');
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_no');
            $table->date('paid_on');
            $table->string('method');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('late_fee')->default(0);
            $table->string('status')->default('posted');
        });

        Schema::create('library_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('card_no')->nullable();
            $table->date('member_since')->nullable();
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->unsignedInteger('fine')->default(0);
            $table->string('status');
        });

        Schema::create('bus_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('route');
            $table->string('vehicle')->nullable();
            $table->string('stop');
            $table->string('landmark')->nullable();
            $table->string('pickup_time')->nullable();
            $table->string('drop_time')->nullable();
            $table->string('driver')->nullable();
            $table->string('driver_phone')->nullable();
            $table->unsignedInteger('fare')->default(0);
            $table->string('since')->nullable();
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('serial_no');
            $table->string('session')->nullable();
            $table->date('issue_date');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('title');
            $table->string('type');
        });

        Schema::create('school_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->date('published_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_notices');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('bus_assignments');
        Schema::dropIfExists('library_loans');
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('homework_items');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('class_periods');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('student_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
