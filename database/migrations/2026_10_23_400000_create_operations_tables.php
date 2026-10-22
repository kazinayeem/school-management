<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind')->default('fingerprint');
            $table->string('location')->nullable();
            $table->string('serial')->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('device_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('card');
            $table->string('code');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['kind', 'code']);
        });

        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->dateTime('punched_at');
            $table->string('direction')->default('in');
            $table->timestamps();
        });

        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind')->default('other');
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->date('issued_on');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('days_per_year')->default(10);
            $table->boolean('paid')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('bell_periods', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_break')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('substitutions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('class_name');
            $table->string('section');
            $table->string('period_label');
            $table->string('original_teacher')->nullable();
            $table->foreignId('substitute_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind');
            $table->string('calc')->default('fixed');
            $table->unsignedInteger('value')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('basic');
            $table->timestamps();
        });

        Schema::create('salary_structure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('salary_structures')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('payroll_components')->cascadeOnDelete();
            $table->unsignedInteger('value');
            $table->unique(['structure_id', 'component_id']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('month')->unique();
            $table->string('status')->default('draft');
            $table->date('paid_on')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('basic');
            $table->unsignedInteger('earnings')->default(0);
            $table->unsignedInteger('deductions')->default(0);
            $table->integer('net');
            $table->unique(['run_id', 'user_id']);
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('body', 500);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('recipient')->nullable();
            $table->string('message', 500);
            $table->string('status')->default('queued');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject');
            $table->string('detail', 500)->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('permission');
            $table->unique(['role', 'permission']);
        });

        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->string('grade')->unique();
            $table->unsignedInteger('min_marks');
            $table->unsignedInteger('max_marks');
            $table->decimal('point', 3, 2);
            $table->timestamps();
        });

        $now = now();
        $ts = ['created_at' => $now, 'updated_at' => $now];

        foreach ([['Casual Leave', 10], ['Sick Leave', 14], ['Annual Leave', 20]] as [$name, $days]) {
            DB::table('leave_types')->insert(['name' => $name, 'days_per_year' => $days, 'paid' => true, 'active' => true] + $ts);
        }

        foreach ([['1st Period', '08:00', '08:45', 0], ['2nd Period', '08:45', '09:30', 0], ['3rd Period', '09:30', '10:15', 0], ['Break', '10:15', '10:45', 1], ['4th Period', '10:45', '11:30', 0], ['5th Period', '11:30', '12:15', 0], ['6th Period', '12:15', '13:00', 0]] as $i => [$label, $from, $to, $break]) {
            DB::table('bell_periods')->insert(['label' => $label, 'starts_at' => $from, 'ends_at' => $to, 'is_break' => $break, 'sort_order' => $i + 1] + $ts);
        }

        foreach ([['House Rent', 'earning', 'percent', 40], ['Medical Allowance', 'earning', 'fixed', 500], ['Provident Fund', 'deduction', 'percent', 5], ['Income Tax', 'deduction', 'fixed', 0]] as [$name, $kind, $calc, $value]) {
            DB::table('payroll_components')->insert(['name' => $name, 'kind' => $kind, 'calc' => $calc, 'value' => $value, 'active' => true] + $ts);
        }

        DB::table('sms_templates')->insert([
            ['name' => 'Fee due reminder', 'body' => 'Dear {guardian}, the fee for {student} ({class}) is due. Please pay at the school office.', 'active' => true] + $ts,
            ['name' => 'Absence alert', 'body' => 'Dear {guardian}, {student} ({class}) was absent today.', 'active' => true] + $ts,
            ['name' => 'General notice', 'body' => 'Dear {guardian}, a new notice has been published for {class}. Please check the school website.', 'active' => true] + $ts,
        ]);

        DB::table('certificate_templates')->insert([
            ['name' => 'Character Certificate', 'kind' => 'character', 'body' => "This is to certify that {student_name}, son/daughter of {guardian}, is a student of {class} (Roll {roll}, Admission No. {admission_no}) at this school. To the best of our knowledge, the student bears a good moral character.\n\nIssued on {date}.", 'active' => true] + $ts,
            ['name' => 'Testimonial', 'kind' => 'testimonial', 'body' => "{student_name} (Admission No. {admission_no}) has been a student of {class} at this school. We wish the student every success.\n\nIssued on {date}.", 'active' => true] + $ts,
            ['name' => 'Transfer Certificate', 'kind' => 'transfer', 'body' => "This is to certify that {student_name} (Admission No. {admission_no}) was a student of {class} at this school and is allowed to transfer.\n\nIssued on {date}.", 'active' => true] + $ts,
        ]);

        foreach ([['A+', 80, 100, 5.00], ['A', 70, 79, 4.00], ['A-', 60, 69, 3.50], ['B', 50, 59, 3.00], ['C', 40, 49, 2.00], ['D', 33, 39, 1.00], ['F', 0, 32, 0.00]] as [$grade, $min, $max, $point]) {
            DB::table('grade_scales')->insert(['grade' => $grade, 'min_marks' => $min, 'max_marks' => $max, 'point' => $point] + $ts);
        }

        foreach (['academic', 'timetable', 'attendance', 'homework', 'exams', 'students', 'leave', 'library', 'notices'] as $permission) {
            DB::table('role_permissions')->insert(['role' => 'staff', 'permission' => $permission]);
        }
    }

    public function down(): void
    {
        foreach (['grade_scales', 'role_permissions', 'audit_logs', 'sms_logs', 'sms_templates', 'payslips', 'payroll_runs', 'salary_structure_items', 'salary_structures', 'payroll_components', 'substitutions', 'bell_periods', 'leave_types', 'certificates', 'certificate_templates', 'punches', 'device_credentials', 'devices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
