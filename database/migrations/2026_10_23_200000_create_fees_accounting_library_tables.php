<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('class_name')->nullable();
            $table->unsignedInteger('amount');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->unsignedInteger('value');
            $table->string('reason')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('online_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('fee_invoices')->nullOnDelete();
            $table->string('gateway');
            $table->string('reference')->unique();
            $table->unsignedInteger('amount');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();
            $table->date('date');
            $table->string('type');
            $table->string('narration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
        });

        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('isbn')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $table->unsignedInteger('copies')->default(1);
            $table->string('shelf')->nullable();
            $table->timestamps();
        });

        Schema::create('library_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('card_no')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('library_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('reserved_on');
            $table->string('status')->default('waiting');
            $table->timestamps();
        });

        Schema::table('library_loans', function (Blueprint $table) {
            $table->unsignedBigInteger('book_id')->nullable();
            $table->boolean('fine_paid')->default(false);
        });

        $now = now();
        DB::table('accounts')->insert(array_map(fn ($a) => ['code' => $a[0], 'name' => $a[1], 'type' => $a[2], 'active' => true, 'created_at' => $now, 'updated_at' => $now], [
            ['1001', 'Cash in Hand', 'asset'],
            ['1002', 'Bank Account', 'asset'],
            ['2001', 'Accounts Payable', 'liability'],
            ['3001', 'Capital Fund', 'equity'],
            ['4001', 'Tuition Fee Income', 'income'],
            ['4002', 'Admission Fee Income', 'income'],
            ['4003', 'Other Income', 'income'],
            ['5001', 'Salary Expense', 'expense'],
            ['5002', 'Utilities Expense', 'expense'],
            ['5003', 'Stationery Expense', 'expense'],
            ['5004', 'Maintenance Expense', 'expense'],
        ]));

        DB::table('fee_heads')->insert([
            ['name' => 'Monthly Tuition', 'class_name' => null, 'amount' => 1500, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Exam Fee', 'class_name' => null, 'amount' => 500, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('library_categories')->insert([
            ['name' => 'Textbook', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Story & Fiction', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Science', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('library_loans', function (Blueprint $table) {
            $table->dropColumn(['book_id', 'fine_paid']);
        });
        foreach (['library_reservations', 'library_members', 'library_books', 'library_categories', 'voucher_lines', 'vouchers', 'accounts', 'online_transactions', 'fee_waivers', 'fee_heads'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
