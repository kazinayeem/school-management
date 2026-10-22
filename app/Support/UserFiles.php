<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Admin-uploaded papers (birth certificate, NID, earlier certificates). Private: served only through an authorised route. */
class UserFiles
{
    public const KINDS = [
        'birth_certificate' => 'Birth certificate',
        'nid' => 'National ID (NID)',
        'previous_certificate' => 'Previous school certificate',
        'transfer_certificate' => 'Transfer certificate',
        'passport' => 'Passport',
        'other' => 'Other',
    ];

    public const MAX_KB = 5120;
    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('local');
    }

    public static function rules(): array
    {
        return ['file', 'mimes:'.implode(',', self::MIMES), 'max:'.self::MAX_KB];
    }

    public static function save(UploadedFile $file, User $owner, string $kind, ?string $title, ?int $by): int
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs("user-files/{$owner->id}", Str::uuid().'.'.$ext, 'local');

        return DB::table('user_files')->insertGetId([
            'user_id' => $owner->id, 'kind' => $kind, 'title' => $title ?: (self::KINDS[$kind] ?? 'Document'), 'path' => $path,
            'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(), 'uploaded_by' => $by, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Save the optional doc_<kind> uploads that come with an add-student / add-staff form. */
    public static function saveFromRequest(Request $request, User $owner): void
    {
        foreach (array_keys(self::KINDS) as $kind) {
            if ($request->hasFile("doc_{$kind}")) {
                self::save($request->file("doc_{$kind}"), $owner, $kind, null, $request->user()->id);
            }
        }
    }

    public static function requestRules(): array
    {
        $rules = [];
        foreach (array_keys(self::KINDS) as $kind) {
            $rules["doc_{$kind}"] = ['nullable', ...self::rules()];
        }

        return $rules;
    }

    /** @return list<array<string, mixed>> */
    public static function listFor(int $userId): array
    {
        return DB::table('user_files')->where('user_id', $userId)->orderBy('kind')->orderByDesc('id')->get()->map(fn ($f) => [
            'id' => $f->id, 'kind' => $f->kind, 'kind_label' => self::KINDS[$f->kind] ?? 'Document', 'title' => $f->title, 'mime' => $f->mime, 'size' => (int) $f->size,
            'uploaded_at' => substr((string) $f->created_at, 0, 10), 'url' => "/files/{$f->id}",
        ])->all();
    }

    /** Admin sees everything. A person sees their own. A parent sees their child's. Office staff and teachers see a student's they may open. */
    public static function canView(User $viewer, object $file): bool
    {
        if ($viewer->role === 'admin' || (int) $file->user_id === $viewer->id) {
            return true;
        }
        $owner = User::find($file->user_id);
        if ($owner === null || $owner->role !== 'student') {
            return false;
        }
        if ($viewer->role === 'parent') {
            return DB::table('guardian_student')->where('guardian_id', $viewer->id)->where('student_id', $owner->id)->exists();
        }
        if (in_array($viewer->role, ['teacher', 'staff'], true)) {
            return Permissions::canRole($viewer->role, 'students', 'view') && (! TeacherScope::applies($viewer) || TeacherScope::canSee($viewer, $owner->id));
        }

        return false;
    }

    public static function deleteFile(object $file): void
    {
        self::disk()->delete($file->path);
        DB::table('user_files')->where('id', $file->id)->delete();
    }
}
