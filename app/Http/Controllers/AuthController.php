<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\PhoneOtpService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->e164Phone(),
            'password' => $request->validated('password'),
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        // A phone typed at registration is only a contact number until it is
        // confirmed by SMS code; say so, or "forgot password by SMS" would
        // silently never send anything for this account.
        return redirect()->route('account.bookings')->with('success', 'Welcome, '.$user->name.'! Your account has been created.'
            .($user->phone ? ' To use your phone number for password recovery, verify it at My Account → Phone Number.' : ''));
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('account.bookings'));
        }

        throw ValidationException::withMessages([
            'email' => 'These credentials do not match our records.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request)
    {
        // Same response whether the email is unknown, just sent, or still in
        // the broker's per-account throttle window, so this form can't be
        // used to discover which emails are registered (as in the admin and
        // phone recovery flows). Request volume is capped by the route throttle.
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'If an account exists for that email address, we have sent it a password reset link.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, PhoneOtpService $otps)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($otps) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Any outstanding SMS reset code for this account is now stale too.
                $otps->invalidateForUser($user, PhoneOtp::PURPOSE_PASSWORD_RESET);

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
        }

        // One message for unknown email, bad or expired token alike, so a
        // forged token can't be used to probe which emails are registered.
        throw ValidationException::withMessages([
            'email' => 'This password reset link is invalid or has expired. Please request a new one.',
        ]);
    }
}
