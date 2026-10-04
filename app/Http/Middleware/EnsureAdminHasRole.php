<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side role gate for admin routes — usage: ->middleware('admin.role:super_admin,admin').
 * Never relies on hiding a sidebar link; an admin without the required
 * role gets a 403 even if they type/bookmark the URL directly.
 */
class EnsureAdminHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = Auth::guard('admin')->user();

        abort_if(! $admin || ! in_array($admin->role, $roles, true), 403);

        return $next($request);
    }
}
