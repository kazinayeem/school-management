<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [\App\Http\Controllers\PublicController::class, 'home'])->name('home');
Route::get('about', [\App\Http\Controllers\PublicController::class, 'page'])->defaults('slug', 'about');
Route::get('facilities', [\App\Http\Controllers\PublicController::class, 'page'])->defaults('slug', 'facilities');
Route::get('principal-message', [\App\Http\Controllers\PublicController::class, 'page'])->defaults('slug', 'principal-message');
Route::get('mission-and-vision', [\App\Http\Controllers\PublicController::class, 'page'])->defaults('slug', 'mission-and-vision');
Route::get('school-uniform', [\App\Http\Controllers\PublicController::class, 'page'])->defaults('slug', 'school-uniform');
Route::get('privacy-policy', [\App\Http\Controllers\PublicController::class, 'page'])->defaults('slug', 'privacy-policy');
Route::get('terms-and-conditions', [\App\Http\Controllers\PublicController::class, 'page'])->defaults('slug', 'terms-and-conditions');
Route::get('teachers', [\App\Http\Controllers\PublicController::class, 'teachers']);
Route::get('gallery', [\App\Http\Controllers\PublicController::class, 'gallery']);
Route::get('notices', [\App\Http\Controllers\PublicController::class, 'notices']);
Route::get('notices/{notice}', [\App\Http\Controllers\PublicController::class, 'notice'])->whereNumber('notice');
Route::get('events', [\App\Http\Controllers\PublicController::class, 'events']);
Route::get('events/{event}', [\App\Http\Controllers\PublicController::class, 'event']);
Route::get('contact', [\App\Http\Controllers\PublicController::class, 'contact']);
Route::post('contact', [\App\Http\Controllers\PublicController::class, 'storeContact'])->name('contact.store');
Route::get('admission', [\App\Http\Controllers\PublicController::class, 'admission']);
Route::post('admission', [\App\Http\Controllers\PublicController::class, 'storeAdmission'])->name('admission.store');
Route::post('admission/track', [\App\Http\Controllers\PublicController::class, 'trackAdmission'])->name('admission.track');
Route::get('results', [\App\Http\Controllers\PublicController::class, 'results']);

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');
    Route::controller(\App\Http\Controllers\FeesController::class)->prefix('app/fees')->group(function () {
        Route::get('generate', 'generateForm')->name('fees.generate');
        Route::post('generate', 'generate')->name('fees.generate.run');
        Route::get('waivers', 'waivers')->name('fees.waivers');
        Route::post('waivers', 'storeWaiver')->name('fees.waivers.store');
        Route::put('waivers/{id}', 'toggleWaiver')->whereNumber('id')->name('fees.waivers.toggle');
        Route::delete('waivers/{id}', 'destroyWaiver')->whereNumber('id')->name('fees.waivers.destroy');
        Route::get('dues', 'dues')->name('fees.dues');
        Route::post('dues/remind', 'remindDues')->name('fees.dues.remind');
        Route::get('payments', 'payments')->name('fees.payments');
        Route::get('payments/export', 'exportPayments')->name('fees.payments.export');
        Route::get('online', 'online')->name('fees.online');
        Route::post('online', 'storeOnline')->name('fees.online.store');
        Route::put('online/{id}', 'reconcile')->whereNumber('id')->name('fees.online.reconcile');
    });

    Route::controller(\App\Http\Controllers\StaffController::class)->prefix('app/staff')->group(function () {
        Route::get('/', 'index')->name('staff.index');
        Route::get('new', 'create')->name('staff.create');
        Route::post('/', 'store')->name('staff.store');
        Route::get('{id}', 'edit')->whereNumber('id')->name('staff.edit');
        Route::put('{id}', 'update')->whereNumber('id')->name('staff.update');
        Route::put('{id}/status', 'status')->whereNumber('id')->name('staff.status');
    });

    Route::get('app/lookup/students', [\App\Http\Controllers\AccountsController::class, 'studentSearch'])->name('lookup.students');

    Route::controller(\App\Http\Controllers\AccountsController::class)->prefix('app/system')->group(function () {
        Route::get('parents', 'parents')->name('accounts.parents');
        Route::post('parents', 'storeParent')->name('accounts.parents.store');
        Route::put('parents/{id}', 'updateParent')->whereNumber('id')->name('accounts.parents.update');
        Route::get('users', 'users')->name('accounts.users');
        Route::post('users', 'storeAdmin')->name('accounts.users.store');
        Route::put('users/{id}/status', 'status')->whereNumber('id')->name('accounts.users.status');
        Route::put('users/{id}/password', 'password')->whereNumber('id')->name('accounts.users.password');
    });

    Route::controller(\App\Http\Controllers\TransportController::class)->prefix('app/transport')->group(function () {
        Route::get('/', 'riders')->name('transport.riders');
        Route::post('riders', 'assign')->name('transport.assign');
        Route::delete('riders/{userId}', 'unassign')->whereNumber('userId')->name('transport.unassign');
        Route::get('manifest', 'manifest')->name('transport.manifest');
        Route::get('routes', 'routes')->name('transport.routes');
        Route::post('routes', 'storeRoute')->name('transport.routes.store');
        Route::put('routes/{id}', 'updateRoute')->whereNumber('id')->name('transport.routes.update');
        Route::delete('routes/{id}', 'destroyRoute')->whereNumber('id')->name('transport.routes.destroy');
        Route::post('stops', 'storeStop')->name('transport.stops.store');
        Route::put('stops/{id}', 'updateStop')->whereNumber('id')->name('transport.stops.update');
        Route::delete('stops/{id}', 'destroyStop')->whereNumber('id')->name('transport.stops.destroy');
    });

    Route::post('photos/{user}', [\App\Http\Controllers\PhotoController::class, 'store'])->whereNumber('user')->name('photos.store');
    Route::delete('photos/{user}', [\App\Http\Controllers\PhotoController::class, 'destroy'])->whereNumber('user')->name('photos.destroy');

    Route::get('app/attendance', [\App\Http\Controllers\TakeAttendanceController::class, 'index'])->name('attendance.take');
    Route::post('app/attendance/take', [\App\Http\Controllers\TakeAttendanceController::class, 'store'])->name('attendance.take.store');

    Route::controller(\App\Http\Controllers\AttendanceController::class)->prefix('app/attendance')->group(function () {
        Route::get('register', 'register')->name('attendance.register');
        Route::post('punches', 'syncPunches')->name('attendance.punches');
        Route::get('staff', 'staff')->name('attendance.staff');
        Route::post('staff', 'saveStaff')->name('attendance.staff.save');
        Route::post('staff/punches', 'syncStaffPunches')->name('attendance.staff.punches');
    });

    Route::controller(\App\Http\Controllers\ExamsController::class)->prefix('app/exams')->group(function () {
        Route::get('/', 'setup')->name('exams.setup');
        Route::post('/', 'storeExam')->name('exams.store');
        Route::put('{id}', 'updateExam')->whereNumber('id')->name('exams.update');
        Route::delete('{id}', 'destroyExam')->whereNumber('id')->name('exams.destroy');
        Route::post('schedule', 'saveSchedule')->name('exams.schedule');
        Route::get('admit-cards', 'admitCards')->name('exams.admit');
        Route::get('marks', 'marks')->name('exams.marks');
        Route::post('marks', 'saveMarks')->name('exams.marks.save');
        Route::get('results', 'results')->name('exams.results');
        Route::post('publish', 'publish')->name('exams.publish');
        Route::post('unpublish', 'unpublish')->name('exams.unpublish');
    });

    Route::controller(\App\Http\Controllers\TimetableController::class)->prefix('app/timetable')->group(function () {
        Route::get('/', 'routine')->name('timetable.routine');
        Route::post('/', 'saveRoutine')->name('timetable.routine.save');
        Route::get('teacher', 'teacher')->name('timetable.teacher');
        Route::get('substitutions', 'substitutions')->name('timetable.substitutions');
        Route::post('substitutions', 'storeSubstitution')->name('timetable.substitutions.store');
        Route::delete('substitutions/{id}', 'destroySubstitution')->whereNumber('id')->name('timetable.substitutions.destroy');
    });

    Route::controller(\App\Http\Controllers\CertificatesController::class)->prefix('app/certificates')->group(function () {
        Route::get('/', 'issueForm')->name('certificates.issue');
        Route::post('/', 'issue')->name('certificates.issue.run');
        Route::get('history', 'history')->name('certificates.history');
        Route::get('{id}/print', 'print')->whereNumber('id')->name('certificates.print');
    });

    Route::controller(\App\Http\Controllers\PayrollController::class)->prefix('app/payroll')->group(function () {
        Route::get('/', 'runs')->name('payroll.runs');
        Route::post('/', 'generate')->name('payroll.generate');
        Route::put('{id}/pay', 'pay')->whereNumber('id')->name('payroll.pay');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('payroll.destroy');
        Route::get('structures', 'structures')->name('payroll.structures');
        Route::post('structures', 'saveStructure')->name('payroll.structures.save');
    });

    Route::controller(\App\Http\Controllers\SmsController::class)->prefix('app/sms')->group(function () {
        Route::get('/', 'compose')->name('sms.compose');
        Route::post('/', 'send')->name('sms.send');
        Route::get('log', 'log')->name('sms.log');
        Route::put('log/{id}', 'mark')->whereNumber('id')->name('sms.mark');
        Route::post('log/deliver', 'deliverQueued')->name('sms.deliver');
    });

    Route::controller(\App\Http\Controllers\SystemController::class)->group(function () {
        Route::get('app/system/audit', 'audit')->name('system.audit');
        Route::get('app/system/roles', 'roles')->name('system.roles');
        Route::post('app/system/roles', 'saveRoles')->name('system.roles.save');
    });

    Route::controller(\App\Http\Controllers\BackupController::class)->prefix('app/settings/backup')->group(function () {
        Route::get('/', 'index')->name('backup.index');
        Route::post('/', 'create')->name('backup.create');
        Route::get('{name}', 'download')->name('backup.download');
        Route::delete('{name}', 'destroy')->name('backup.destroy');
        Route::post('{name}/restore', 'restore')->name('backup.restore');
    });

    Route::post('app/settings/mail/test', [\App\Http\Controllers\SettingsFormController::class, 'testMail'])->name('settings.mail.test');

    Route::controller(\App\Http\Controllers\SettingsFormController::class)->group(function () {
        foreach (['general' => '/app/settings/general', 'attendance' => '/app/settings/attendance', 'fees' => '/app/settings/fees', 'mail' => '/app/settings/mail', 'appearance' => '/app/settings/appearance', 'languages' => '/app/settings/languages', 'smsgateway' => '/app/sms/settings'] as $form => $path) {
            Route::get($path, 'show')->defaults('form', $form)->name("settings.{$form}");
            Route::post($path, 'save')->defaults('form', $form);
        }

        Route::get('app/fees/gateway', 'show')->defaults('form', 'gateway')->name('settings.gateway');
        Route::post('app/fees/gateway', 'save')->defaults('form', 'gateway');
        Route::get('app/library/settings', 'show')->defaults('form', 'library')->name('settings.library');
        Route::post('app/library/settings', 'save')->defaults('form', 'library');
    });

    Route::controller(\App\Http\Controllers\AccountingController::class)->prefix('app/accounting')->group(function () {
        Route::get('/', 'voucherForm')->name('accounting.voucher');
        Route::post('vouchers', 'storeVoucher')->name('accounting.voucher.store');
        Route::delete('vouchers/{id}', 'destroyVoucher')->whereNumber('id')->name('accounting.voucher.destroy');
        Route::get('register', 'register')->name('accounting.register');
        Route::get('cash-book', 'cashBook')->name('accounting.cashbook');
        Route::get('trial-balance', 'trialBalance')->name('accounting.trial');
        Route::get('income-expense', 'incomeExpense')->name('accounting.income');
    });

    Route::controller(\App\Http\Controllers\LibraryController::class)->prefix('app/library')->group(function () {
        Route::get('/', 'give')->name('library.give');
        Route::post('loans', 'storeLoan')->name('library.loan.store');
        Route::get('desk', 'desk')->name('library.desk');
        Route::put('loans/{id}/return', 'returnLoan')->whereNumber('id')->name('library.loan.return');
        Route::get('fines', 'fines')->name('library.fines');
        Route::put('loans/{id}/fine', 'payFine')->whereNumber('id')->name('library.fine.pay');
        Route::get('reservations', 'reservations')->name('library.reservations');
        Route::post('reservations', 'storeReservation')->name('library.reservations.store');
        Route::put('reservations/{id}', 'updateReservation')->whereNumber('id')->name('library.reservations.update');
    });

    Route::controller(\App\Http\Controllers\WebsiteController::class)->prefix('app/devices')->group(function () {
        Route::get('/', 'baseIndex')->defaults('group', 'devices')->defaults('resource', 'devices')->name('devices.index');
        Route::post('/', 'baseStore')->defaults('group', 'devices')->defaults('resource', 'devices')->name('devices.store');
        Route::put('{id}', 'baseUpdate')->whereNumber('id')->defaults('group', 'devices')->defaults('resource', 'devices')->name('devices.update');
        Route::delete('{id}', 'baseDestroy')->whereNumber('id')->defaults('group', 'devices')->defaults('resource', 'devices')->name('devices.destroy');
    });

    foreach (['academic' => \App\Support\AcademicResources::slugs(), 'fees' => \App\Support\OperationsResources::slugs('fees'), 'accounting' => \App\Support\OperationsResources::slugs('accounting'), 'library' => \App\Support\OperationsResources::slugs('library'), 'devices' => \App\Support\OperationsResources::slugs('devices'), 'certificates' => \App\Support\OperationsResources::slugs('certificates'), 'leave' => \App\Support\OperationsResources::slugs('leave'), 'timetable' => \App\Support\OperationsResources::slugs('timetable'), 'payroll' => \App\Support\OperationsResources::slugs('payroll'), 'sms' => \App\Support\OperationsResources::slugs('sms'), 'settings' => \App\Support\OperationsResources::slugs('settings'), 'transport' => \App\Support\OperationsResources::slugs('transport')] as $group => $slugs) {
        Route::prefix("app/{$group}")->controller(\App\Http\Controllers\WebsiteController::class)->group(function () use ($group, $slugs) {
            Route::get('{resource}', 'index')->where('resource', $slugs)->defaults('group', $group)->name("{$group}.index");
            Route::post('{resource}', 'store')->where('resource', $slugs)->defaults('group', $group)->name("{$group}.store");
            Route::put('{resource}/{id}', 'update')->where('resource', $slugs)->whereNumber('id')->defaults('group', $group)->name("{$group}.update");
            Route::delete('{resource}/{id}', 'destroy')->where('resource', $slugs)->whereNumber('id')->defaults('group', $group)->name("{$group}.destroy");
        });
    }

    Route::controller(\App\Http\Controllers\StudentToolsController::class)->prefix('app/students')->group(function () {
        Route::get('import', 'importForm')->name('students.import');
        Route::get('import/template', 'importTemplate')->name('students.import.template');
        Route::post('import', 'import')->name('students.import.run');
        Route::get('roll-numbers', 'rollNumbers')->name('students.rolls');
        Route::post('roll-numbers', 'saveRollNumbers')->name('students.rolls.save');
        Route::get('id-cards', 'idCards')->name('students.idcards');
        Route::get('promotion', 'promotionForm')->name('students.promotion');
        Route::post('promotion', 'promote')->name('students.promotion.run');
    });

    Route::controller(\App\Http\Controllers\AdmissionController::class)->prefix('app/admissions')->group(function () {
        Route::get('/', 'index')->name('admissions.index');
        Route::get('settings', 'settings')->name('admissions.settings');
        Route::post('settings', 'saveSettings')->name('admissions.settings.save');
        Route::put('{id}', 'decide')->whereNumber('id')->name('admissions.decide');
    });

    Route::controller(\App\Http\Controllers\WebsiteMessagesController::class)->prefix('app/website/messages')->group(function () {
        Route::get('/', 'index')->name('website.messages');
        Route::put('{id}', 'update')->whereNumber('id')->name('website.messages.update');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('website.messages.destroy');
    });

    Route::prefix('app/website')->controller(\App\Http\Controllers\WebsiteController::class)->group(function () {
        Route::post('settings', 'saveSettings')->name('website.settings.save');
        Route::get('{resource}', 'index')->where('resource', 'pages|banners|events|gallery|testimonials|faqs|settings')->name('website.index');
        Route::post('{resource}', 'store')->where('resource', 'pages|banners|events|gallery|testimonials|faqs')->name('website.store');
        Route::put('{resource}/{id}', 'update')->where('resource', 'pages|banners|events|gallery|testimonials|faqs')->whereNumber('id')->name('website.update');
        Route::delete('{resource}/{id}', 'destroy')->where('resource', 'pages|banners|events|gallery|testimonials|faqs')->whereNumber('id')->name('website.destroy');
    });
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
