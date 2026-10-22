<?php

namespace App\Http\Controllers;

use App\Support\Comms;
use App\Support\UserFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttachmentController extends Controller
{
    public function show(Request $request, int $id)
    {
        $a = DB::table('attachments')->find($id);
        abort_if($a === null || ! Comms::canOpenAttachment($request->user(), $a), 404);

        return UserFiles::disk()->response($a->path, $a->name, ['Content-Type' => $a->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0'], $request->boolean('download') ? 'attachment' : 'inline');
    }
}
