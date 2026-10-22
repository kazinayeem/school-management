<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AcademicResources
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'sessions' => [
                'label' => 'Sessions',
                'subtitle' => 'Academic years. Exactly one session can be the current one.',
                'table' => 'academic_sessions',
                'order' => ['starts_on', 'desc'],
                'columns' => ['name', 'starts_on', 'ends_on', 'is_current'],
                'column_labels' => ['is_current' => 'Current'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:50|unique:academic_sessions,name'],
                    ['name' => 'starts_on', 'label' => 'Starts on', 'type' => 'date', 'rules' => 'required|date'],
                    ['name' => 'ends_on', 'label' => 'Ends on', 'type' => 'date', 'rules' => 'required|date|after:starts_on'],
                    ['name' => 'is_current', 'label' => 'Current session', 'type' => 'checkbox', 'default' => false],
                ],
            ],
            'classes' => [
                'label' => 'Classes',
                'subtitle' => 'The classes the school teaches, in teaching order.',
                'table' => 'academic_classes',
                'order' => ['sort_order', 'asc'],
                'columns' => ['name', 'sort_order'],
                'column_labels' => ['sort_order' => 'Order'],
                'delete_guard' => ['academic_sections', 'class_name', 'Remove this class\'s sections first.', 'name'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:50|unique:academic_classes,name'],
                    ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'rules' => 'required|integer|min:0', 'default' => 0],
                ],
            ],
            'sections' => [
                'label' => 'Sections',
                'subtitle' => 'Sections of each class and how many students they can hold.',
                'table' => 'academic_sections',
                'order' => ['class_name', 'asc'],
                'columns' => ['class_name', 'name', 'capacity'],
                'column_labels' => ['class_name' => 'Class', 'name' => 'Section'],
                'query' => fn () => DB::table('academic_sections')
                    ->orderByRaw("cast(replace(class_name, 'Class ', '') as integer)")->orderBy('name'),
                'fields' => [
                    ['name' => 'class_name', 'label' => 'Class', 'type' => 'select', 'options_query' => ['academic_classes', 'name', 'name'], 'rules' => 'required|string|exists:academic_classes,name'],
                    ['name' => 'name', 'label' => 'Section', 'type' => 'text', 'rules' => 'required|string|max:10'],
                    ['name' => 'capacity', 'label' => 'Capacity', 'type' => 'number', 'rules' => 'required|integer|min:1|max:200', 'default' => 40],
                ],
            ],
            'subjects' => [
                'label' => 'Subjects',
                'subtitle' => 'Subjects with their marks. Leave the class empty for every class.',
                'table' => 'academic_subjects',
                'order' => ['name', 'asc'],
                'columns' => ['name', 'code', 'class_name', 'kind', 'full_marks', 'pass_marks', 'active'],
                'column_labels' => ['class_name' => 'Class', 'kind' => 'Type', 'full_marks' => 'Full marks', 'pass_marks' => 'Pass marks'],
                'delete_guard' => ['teaching_assignments', 'subject_id', 'This subject is assigned to a teacher.'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100'],
                    ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'rules' => 'required|string|max:20|unique:academic_subjects,code'],
                    ['name' => 'class_name', 'label' => 'Class', 'type' => 'select', 'options_query' => ['academic_classes', 'name', 'name', 'All classes'], 'rules' => 'nullable|string|exists:academic_classes,name'],
                    ['name' => 'kind', 'label' => 'Type', 'type' => 'select', 'rules' => 'required|in:theory,practical', 'options' => [['value' => 'theory', 'label' => 'Theory'], ['value' => 'practical', 'label' => 'Practical']]],
                    ['name' => 'full_marks', 'label' => 'Full marks', 'type' => 'number', 'rules' => 'required|integer|min:1|max:1000', 'default' => 100],
                    ['name' => 'pass_marks', 'label' => 'Pass marks', 'type' => 'number', 'rules' => 'required|integer|min:0|lte:full_marks', 'default' => 33],
                    ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'assignments' => [
                'label' => 'Teaching Assignments',
                'subtitle' => 'Which teacher teaches which subject to which class.',
                'table' => 'teaching_assignments',
                'order' => ['id', 'desc'],
                'columns' => ['teacher_name', 'class_name', 'section', 'subject_name'],
                'column_labels' => ['teacher_name' => 'Teacher', 'class_name' => 'Class', 'section' => 'Section', 'subject_name' => 'Subject'],
                'query' => fn () => DB::table('teaching_assignments')
                    ->join('users', 'users.id', '=', 'teaching_assignments.user_id')
                    ->join('academic_subjects', 'academic_subjects.id', '=', 'teaching_assignments.subject_id')
                    ->select('teaching_assignments.*', 'users.name as teacher_name', 'academic_subjects.name as subject_name')
                    ->orderBy('users.name')->orderBy('teaching_assignments.class_name'),
                'fields' => [
                    ['name' => 'user_id', 'label' => 'Teacher', 'type' => 'select', 'options_query' => 'staff', 'rules' => 'required|integer|exists:users,id'],
                    ['name' => 'class_name', 'label' => 'Class', 'type' => 'select', 'options_query' => ['academic_classes', 'name', 'name'], 'rules' => 'required|string|exists:academic_classes,name'],
                    ['name' => 'section', 'label' => 'Section', 'type' => 'select', 'rules' => 'nullable|in:A,B', 'options' => [['value' => '', 'label' => 'All sections'], ['value' => 'A', 'label' => 'A'], ['value' => 'B', 'label' => 'B']]],
                    ['name' => 'subject_id', 'label' => 'Subject', 'type' => 'select', 'options_query' => ['academic_subjects', 'id', 'name'], 'rules' => 'required|integer|exists:academic_subjects,id'],
                ],
            ],
            'groups' => [
                'label' => 'Groups',
                'subtitle' => 'Study groups students can be placed in, such as Science or Commerce.',
                'table' => 'academic_groups',
                'order' => ['name', 'asc'],
                'columns' => ['name', 'description', 'active'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100|unique:academic_groups,name'],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
                    ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'shifts' => [
                'label' => 'Shifts',
                'subtitle' => 'School shifts with their start and end times.',
                'table' => 'academic_shifts',
                'order' => ['starts_at', 'asc'],
                'columns' => ['name', 'starts_at', 'ends_at', 'active'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100|unique:academic_shifts,name'],
                    ['name' => 'starts_at', 'label' => 'Starts at', 'type' => 'time', 'rules' => 'nullable|date_format:H:i'],
                    ['name' => 'ends_at', 'label' => 'Ends at', 'type' => 'time', 'rules' => 'nullable|date_format:H:i|after:starts_at'],
                    ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'mediums' => [
                'label' => 'Mediums',
                'subtitle' => 'Languages of instruction offered by the school.',
                'table' => 'academic_mediums',
                'order' => ['name', 'asc'],
                'columns' => ['name', 'active'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100|unique:academic_mediums,name'],
                    ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                ],
            ],
        ];
    }

    public static function slugs(): string
    {
        return implode('|', array_keys(self::all()));
    }
}
