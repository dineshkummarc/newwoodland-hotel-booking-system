<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Self-service password recovery for staff, on Laravel's standard password
 * broker (the `admins` broker in config/auth.php: its own token table,
 * 30-minute links). Only active staff accounts can reset; the response
 * never reveals whether an email belongs to a staff account.
 */
class PasswordResetController extends Controller
{
    protected const GENERIC_STATUS = 'If an active staff account exists for that email, a password reset link has been sent to it.';

    public function showForgot()
    {
        return view('admin.auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        // is_active is an extra provider constraint: deactivated staff are
        // treated exactly like unknown emails.
        Password::broker('admins')->sendResetLink([
            'email' => $request->input('email'),
            'is_active' => true,
        ]);

        return back()->with('status', self::GENERIC_STATUS);
    }

    public function showReset(Request $request, string $token)
    {
        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request, AuditLogService $auditLog)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $resetAdmin = null;

        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token') + ['is_active' => true],
            function (Admin $admin, string $password) use (&$resetAdmin) {
                $admin->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($admin));
                $resetAdmin = $admin;
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This password reset link is invalid or has expired. Please request a new one.',
            ]);
        }

        $auditLog->log('admin.password_reset', 'admin', $resetAdmin->id, "Staff member {$resetAdmin->email} reset their password using an emailed link.");

        return redirect()->route('admin.login')->with('status', 'Your password has been reset. You can now sign in.');
    }
}
