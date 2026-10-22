<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $fillable = [
        'user_id',
        'class_name',
        'section',
        'roll_no',
        'session',
        'attendance_present',
        'attendance_working',
        'gpa',
        'exam_name',
        'class_position',
        'section_position',
        'promotion_status',
        'dues_total',
        'dues_count',
        'dues_due_date',
    ];

    protected function casts(): array
    {
        return [
            'gpa' => 'float',
            'dues_due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
