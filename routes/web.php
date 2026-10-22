<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');
    Route::post('app/notices', [\App\Http\Controllers\OfficeActionController::class, 'storeNotice'])->name('office.notices.store');
    Route::post('app/attendance', [\App\Http\Controllers\OfficeActionController::class, 'storeAttendance'])->name('office.attendance.store');
    Route::post('app/homework', [\App\Http\Controllers\OfficeActionController::class, 'storeHomework'])->name('office.homework.store');
    Route::post('app/students', [\App\Http\Controllers\OfficeActionController::class, 'storeStudent'])->name('office.students.store');
    Route::post('app/leave/{leave}', [\App\Http\Controllers\OfficeActionController::class, 'decideLeave'])->name('office.leave.decide');
    Route::post('app/fees/{invoice}', [\App\Http\Controllers\OfficeActionController::class, 'collectFee'])->name('office.fees.collect');
    Route::get('app/{page?}', [\App\Http\Controllers\OfficeController::class, 'show'])
        ->where('page', '.*')
        ->name('office');

    Route::post('student/profile', [\App\Http\Controllers\StudentPortalController::class, 'updateProfile'])->name('student.profile.update');
    Route::post('student/password', [\App\Http\Controllers\StudentPortalController::class, 'updatePassword'])->name('student.password.update');
    Route::post('student/leave', [\App\Http\Controllers\StudentPortalController::class, 'storeLeave'])->name('student.leave.store');
    Route::post('student/homework/{homework}', [\App\Http\Controllers\StudentPortalController::class, 'submitHomework'])->name('student.homework.submit');
    Route::get('student/{page?}', [\App\Http\Controllers\StudentPortalController::class, 'show'])
        ->where('page', 'routine|calendar|attendance|homework|results|fees|library|transport|documents|leave|profile')
        ->name('student.portal');

    Route::post('parent/profile', [\App\Http\Controllers\ParentPortalController::class, 'updateProfile'])->name('parent.profile.update');
    Route::post('parent/password', [\App\Http\Controllers\ParentPortalController::class, 'updatePassword'])->name('parent.password.update');
    Route::post('parent/leave', [\App\Http\Controllers\ParentPortalController::class, 'storeLeave'])->name('parent.leave.store');
    Route::get('parent/{page?}', [\App\Http\Controllers\ParentPortalController::class, 'show'])
        ->where('page', 'attendance|routine|calendar|documents|leave|homework|library|transport|profile')
        ->name('parent.portal');

    Route::get('m/{slug}', function (\Illuminate\Http\Request $request, string $slug) {
        $item = \App\Support\RoleMenu::find($request->user()->role, $slug);
        abort_if($item === null, 404);

        return Inertia::render('section', ['title' => $item['title']]);
    })->name('section');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
