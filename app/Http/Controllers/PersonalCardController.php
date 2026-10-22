<?php

namespace App\Http\Controllers;

use App\Support\OfficeMenu;
use App\Support\Permissions;
use App\Support\PersonalCard;
use App\Support\PortalData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Everyone downloads (prints) their own card: ID card, or a visiting pass for parents. */
class PersonalCardController extends Controller
{
    public function office(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'teacher', 'staff'], true), 403);

        return Inertia::render('office/my-card', [
            'navigation' => OfficeMenu::for($user->role),
            'roleLabel' => Permissions::label($user->role),
            'card' => PersonalCard::for($user),
            'userId' => $user->id,
        ]);
    }

    public function student(Request $request): Response
    {
        return $this->portal($request, 'student');
    }

    public function parent(Request $request): Response
    {
        return $this->portal($request, 'parent');
    }

    private function portal(Request $request, string $role): Response
    {
        $user = $request->user();
        abort_unless($user->role === $role, 403);

        return Inertia::render('portals/card', [
            'card' => PersonalCard::for($user),
            'userId' => $user->id,
            'notices' => PortalData::notices(),
        ]);
    }
}
