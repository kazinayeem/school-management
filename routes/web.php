<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');

    Route::get('m/{slug}', function (\Illuminate\Http\Request $request, string $slug) {
        $item = \App\Support\RoleMenu::find($request->user()->role, $slug);
        abort_if($item === null, 404);

        return Inertia::render('section', ['title' => $item['title']]);
    })->name('section');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
