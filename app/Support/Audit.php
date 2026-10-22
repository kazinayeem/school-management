<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Audit
{
    public static function log(string $action, string $subject, ?string $detail = null): void
    {
        $request = request();

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject' => $subject,
            'detail' => $detail === null ? null : mb_substr($detail, 0, 500),
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
