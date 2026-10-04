<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated admin's existing session must not keep working just
 * because it hasn't expired yet — login itself already refuses inactive
 * accounts (see AuthController::login, which includes is_active in the
 * attempt() credentials), but this closes the gap for a session that was
 * already open at the moment an account gets deactivated.
 */
class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && ! $admin->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Your account has been deactivated. Please contact a super admin.');
        }

        return $next($request);
    }
}
