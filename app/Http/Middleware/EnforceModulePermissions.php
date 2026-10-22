<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Teachers and office staff may only use the /app pages their role has been granted:
 * View for reading, Edit for adding or changing, Delete for removing.
 */
class EnforceModulePermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && Permissions::isEmployee($user->role) && ($request->is('app') || $request->is('app/*'))) {
            abort_unless(Permissions::pathAllowed($user->role, '/'.$request->path(), $request->method()), 403);
        }

        return $next($request);
    }
}
