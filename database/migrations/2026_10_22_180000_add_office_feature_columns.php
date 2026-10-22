<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('admission_no')->nullable()->after('user_id');
            $table->string('gender')->nullable()->after('roll_no');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('shift')->nullable()->after('date_of_birth');
            $table->string('guardian_name')->nullable()->after('shift');
            $table->string('guardian_mobile')->nullable()->after('guardian_name');
            $table->string('status')->default('active')->after('guardian_mobile');
            $table->string('religion')->nullable()->after('status');
            $table->string('address')->nullable()->after('religion');
        });

        Schema::table('school_notices', function (Blueprint $table) {
            $table->date('expires_on')->nullable()->after('published_on');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'admission_no',
                'gender',
                'date_of_birth',
                'shift',
                'guardian_name',
                'guardian_mobile',
                'status',
                'religion',
                'address',
            ]);
        });

        Schema::table('school_notices', function (Blueprint $table) {
            $table->dropColumn('expires_on');
        });
    }
};
