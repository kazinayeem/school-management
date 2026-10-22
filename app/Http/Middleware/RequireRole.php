<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Lets a route group through only for the listed roles: ->middleware('roles:teacher,staff'). */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless(in_array($request->user()?->role, $roles, true), 403);

        return $next($request);
    }
}
