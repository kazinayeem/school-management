<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $role = $request->user()->role;

        if ($role === 'student') {
            return redirect('/student');
        }

        if ($role === 'parent') {
            return redirect('/parent');
        }

        return Inertia::render("dashboards/{$role}", match ($role) {
            'admin' => [
                'stats' => [
                    ['label' => 'Total Students', 'value' => '1,248', 'hint' => '+32 this month'],
                    ['label' => 'Teachers & Staff', 'value' => '86', 'hint' => '4 on leave'],
                    ['label' => 'Fees Collected', 'value' => '৳ 12.4L', 'hint' => '78% of target'],
                    ['label' => 'Attendance Today', 'value' => '94%', 'hint' => '1,173 present'],
                ],
                'users' => User::count(),
                'classes' => [
                    ['name' => 'Class 6', 'students' => 160, 'attendance' => 95],
                    ['name' => 'Class 7', 'students' => 172, 'attendance' => 93],
                    ['name' => 'Class 8', 'students' => 168, 'attendance' => 96],
                    ['name' => 'Class 9', 'students' => 150, 'attendance' => 91],
                    ['name' => 'Class 10', 'students' => 144, 'attendance' => 94],
                ],
                'notices' => [
                    ['title' => 'Mid-term exam routine published', 'date' => '20 Oct'],
                    ['title' => 'Parent-teacher meeting on Saturday', 'date' => '18 Oct'],
                    ['title' => 'Fee deadline extended to 30 Oct', 'date' => '15 Oct'],
                ],
            ],
            'staff' => [
                'stats' => [
                    ['label' => 'My Classes Today', 'value' => '5', 'hint' => 'Next: Class 8 at 11:00'],
                    ['label' => 'Students Taught', 'value' => '212', 'hint' => 'across 5 sections'],
                    ['label' => 'Pending Homework Checks', 'value' => '37', 'hint' => '3 due today'],
                    ['label' => 'Leave Balance', 'value' => '9 days', 'hint' => 'of 14'],
                ],
                'schedule' => [
                    ['time' => '08:00', 'class' => 'Class 6 · Mathematics', 'room' => 'Room 101'],
                    ['time' => '09:00', 'class' => 'Class 7 · Mathematics', 'room' => 'Room 204'],
                    ['time' => '11:00', 'class' => 'Class 8 · Mathematics', 'room' => 'Room 105'],
                    ['time' => '13:00', 'class' => 'Class 9 · Mathematics', 'room' => 'Room 301'],
                ],
                'notices' => [
                    ['title' => 'Submit mid-term question papers by Friday', 'date' => '21 Oct'],
                    ['title' => 'Staff meeting at 3 PM', 'date' => '19 Oct'],
                ],
            ],
            'student' => [
                'stats' => [
                    ['label' => 'Attendance', 'value' => '92%', 'hint' => '46 of 50 days'],
                    ['label' => 'Latest Result', 'value' => 'A (4.5)', 'hint' => 'Mid-term'],
                    ['label' => 'Homework Due', 'value' => '3', 'hint' => 'this week'],
                    ['label' => 'Fees Due', 'value' => '৳ 1,500', 'hint' => 'by 30 Oct'],
                ],
                'subjects' => [
                    ['name' => 'Bangla', 'marks' => 82],
                    ['name' => 'English', 'marks' => 76],
                    ['name' => 'Mathematics', 'marks' => 91],
                    ['name' => 'Science', 'marks' => 85],
                    ['name' => 'Social Science', 'marks' => 79],
                ],
                'homework' => [
                    ['title' => 'Algebra exercise 4.2', 'due' => 'Tomorrow'],
                    ['title' => 'Science lab report', 'due' => 'Thu'],
                    ['title' => 'English essay', 'due' => 'Sat'],
                ],
            ],
            'parent' => [
                'stats' => [
                    ['label' => 'Children', 'value' => '2', 'hint' => 'enrolled'],
                    ['label' => 'Avg. Attendance', 'value' => '93%', 'hint' => 'this term'],
                    ['label' => 'Fees Due', 'value' => '৳ 3,000', 'hint' => '2 invoices'],
                    ['label' => 'Unread Notices', 'value' => '2', 'hint' => 'from school'],
                ],
                'children' => [
                    ['name' => 'Rahim Uddin', 'class' => 'Class 8 · A', 'attendance' => 95, 'result' => 'A (4.7)'],
                    ['name' => 'Nusrat Jahan', 'class' => 'Class 5 · B', 'attendance' => 91, 'result' => 'A- (3.9)'],
                ],
                'notices' => [
                    ['title' => 'Parent-teacher meeting on Saturday', 'date' => '18 Oct'],
                    ['title' => 'Fee deadline extended to 30 Oct', 'date' => '15 Oct'],
                ],
            ],
        });
    }
}
