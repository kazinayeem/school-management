<?php

namespace App\Http\Controllers;

use App\Support\OfficeData;
use App\Support\OfficeMenu;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    public function show(Request $request, ?string $page = null): Response
    {
        $user = $request->user();
        \App\Support\Permissions::office($request);

        $path = '/app'.($page ? '/'.$page : '');
        abort_unless(OfficeMenu::allows($user->role, $path), 404);

        $shared = [
            'navigation' => OfficeMenu::for($user->role),
            'roleLabel' => \App\Support\Permissions::label($user->role),
        ];

        if ($page === null) {
            return Inertia::render('office/home', [
                ...$shared,
                'dashboard' => OfficeData::home($user),
            ]);
        }

        return Inertia::render('office/module', [
            ...$shared,
            'module' => OfficeData::module($path, $user, $request),
        ]);
    }
}
