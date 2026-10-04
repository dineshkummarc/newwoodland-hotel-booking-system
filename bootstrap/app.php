<?php

use App\Http\Middleware\EnsureAdminHasRole;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureBookingAccess;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Ties every signed-in session (customer and staff) to the account's
        // current password: after a password change or reset, sessions on
        // other devices are logged out on their next request. Laravel's
        // middleware priority runs it after auth:admin on staff routes, so it
        // checks the guard that route uses.
        $middleware->web(append: [AuthenticateSession::class]);

        $middleware->alias([
            'admin.role' => EnsureAdminHasRole::class,
            'admin.active' => EnsureAdminIsActive::class,
            'booking.access' => EnsureBookingAccess::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            return $request->is('admin*')
                ? route('admin.login')
                : route('login');
        });

        $middleware->redirectUsersTo(function ($request) {
            return $request->is('admin*')
                ? route('admin.dashboard')
                : route('account.bookings');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
