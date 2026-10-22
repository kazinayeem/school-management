<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Who may see and download an admit card, and what is on it. */
class AdmitCards
{
    /** Dues above this amount hold the card back. Empty means cards are never held back. */
    public static function limit(): ?int
    {
        $v = SiteSettings::get('admit_due_limit', '');

        return $v === null || trim($v) === '' ? null : max(0, (int) $v);
    }

    public static function due(int $userId): int
    {
        return (int) DB::table('fee_invoices')->where('user_id', $userId)->whereRaw('payable > paid')->selectRaw('coalesce(sum(payable - paid), 0) as d')->value('d');
    }

    /** Exams that have not finished and have a timetable for this class. */
    public static function upcoming(string $class): array
    {
        return DB::table('exams')->where('ends_on', '>=', today()->toDateString())
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('exam_subjects')->whereColumn('exam_subjects.exam_id', 'exams.id')->where('exam_subjects.class_name', $class))
            ->orderBy('starts_on')->get()->all();
    }

    public static function schedule(int $examId, string $class): array
    {
        return DB::table('exam_subjects')->join('academic_subjects', 'academic_subjects.id', '=', 'exam_subjects.subject_id')
            ->where('exam_subjects.exam_id', $examId)->where('exam_subjects.class_name', $class)->orderBy('exam_date')->orderBy('starts_at')
            ->get(['academic_subjects.name as subject', 'exam_subjects.exam_date', 'exam_subjects.starts_at', 'exam_subjects.room'])
            ->map(fn ($r) => ['subject' => $r->subject, 'date' => $r->exam_date, 'time' => substr((string) $r->starts_at, 0, 5), 'room' => $r->room])->all();
    }

    /** @return array{allowed: bool, due: int, limit: int|null, overridden: bool} */
    public static function status(int $userId, int $examId): array
    {
        $limit = self::limit();
        $due = self::due($userId);
        $overridden = DB::table('admit_card_overrides')->where('exam_id', $examId)->where('user_id', $userId)->exists();

        return ['allowed' => $limit === null || $due <= $limit || $overridden, 'due' => $due, 'limit' => $limit, 'overridden' => $overridden];
    }

    /** The student's own page: every upcoming exam, whether the card is open, and the timetable. */
    public static function forStudent(User $student): array
    {
        $profile = $student->profile;
        if (! $profile) {
            return ['exams' => [], 'limit' => self::limit()];
        }

        return ['limit' => self::limit(), 'currency' => SiteSettings::get('currency', 'Tk'), 'exams' => collect(self::upcoming((string) $profile->class_name))->map(fn ($e) => [
            'id' => $e->id, 'name' => $e->name, 'starts_on' => $e->starts_on, 'ends_on' => $e->ends_on,
            'schedule' => self::schedule($e->id, (string) $profile->class_name),
            ...self::status($student->id, $e->id),
        ])->all()];
    }
}
