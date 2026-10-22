<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('style')->default('classic');
            $table->boolean('show_photo')->default(false);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->string('style')->default('classic');
            $table->boolean('show_photo')->default(false);
        });

        DB::table('certificate_templates')->where('kind', 'character')->update(['style' => 'classic']);
        DB::table('certificate_templates')->where('kind', 'testimonial')->update(['style' => 'modern', 'show_photo' => true]);
        DB::table('certificate_templates')->where('kind', 'transfer')->update(['style' => 'elegant', 'show_photo' => true]);

        $now = now();
        DB::table('certificate_templates')->insert([
            ['name' => 'Certificate of Appreciation', 'kind' => 'other', 'style' => 'elegant', 'show_photo' => false, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
                'body' => "This certificate is proudly presented to {student_name} of {class} (Roll {roll}) in recognition of outstanding effort, good conduct and a positive contribution to {school_name}.\n\nGiven on {date}."],
            ['name' => 'Certificate of Merit', 'kind' => 'other', 'style' => 'classic', 'show_photo' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
                'body' => "{student_name}, {class}, Admission No. {admission_no}, has earned this Certificate of Merit for excellent academic achievement at {school_name}.\n\nAwarded on {date}."],
            ['name' => 'Participation Certificate', 'kind' => 'other', 'style' => 'modern', 'show_photo' => false, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
                'body' => "This is to certify that {student_name} of {class} took part with enthusiasm and sincerity in the school programme organised by {school_name}.\n\nIssued on {date}."],
        ]);
    }

    public function down(): void
    {
        DB::table('certificate_templates')->whereIn('name', ['Certificate of Appreciation', 'Certificate of Merit', 'Participation Certificate'])->delete();
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['style', 'show_photo']);
        });
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['style', 'show_photo']);
        });
    }
};
