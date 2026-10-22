<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->unsignedInteger('late_fee')->default(0);
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('detail')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn('detail');
        });
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->dropColumn('late_fee');
        });
    }
};
