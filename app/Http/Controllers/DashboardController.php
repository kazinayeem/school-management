<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $role = $request->user()->role;

        if ($role === 'student') {
            return redirect('/student');
        }

        if ($role === 'parent') {
            return redirect('/parent');
        }

        return redirect('/app');
    }
}
