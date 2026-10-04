<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PhoneResetPasswordRequest;
use App\Http\Requests\Auth\SendPhoneResetCodeRequest;
use App\Http\Requests\Auth\VerifyPhoneCodeRequest;
use App\Models\Admin;
use App\Models\PhoneOtp;
use App\Services\AuditLogService;
use App\Services\PhoneNumberService;
use App\Services\PhoneOtpService;
use App\Services\Sms\SmsException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff password recovery by SMS code, alongside the emailed reset link
 * (PasswordResetController). Mirrors the customer flow
 * (App\Http\Controllers\PhonePasswordResetController):
 *
 *   phone number → code sent → code verified → new password
 *
 * Only an ACTIVE staff account with a VERIFIED phone (Admin → Profile) can
 * recover by SMS, and the staff email must be given too (a phone number
 * alone, e.g. after a SIM swap, is not enough for a staff account). Every response before the code is verified is identical
 * whether or not the number belongs to staff, so it can't be used to find
 * staff phone numbers. State lives in the session ("admin_phone_reset.*").
 */
class PhonePasswordResetController extends Controller
{
    private const PURPOSE = PhoneOtp::PURPOSE_ADMIN_PASSWORD_RESET;

    public function __construct(
        protected PhoneOtpService $otps,
        protected PhoneNumberService $phones,
    ) {}

    public function sendCode(SendPhoneResetCodeRequest $request)
    {
        // Staff accounts need BOTH their email and their verified phone, so
        // control of a phone number alone (e.g. a SIM swap) is not enough.
        $email = $request->validate(['email' => ['required', 'email', 'max:255']])['email'];

        return $this->requestCode($request, $request->e164(), $email);
    }

    public function resendCode(Request $request)
    {
        $phone = $request->session()->get('admin_phone_reset.phone');
        $email = $request->session()->get('admin_phone_reset.email');

        return $phone && $email ? $this->requestCode($request, $phone, $email) : $this->restart();
    }

    public function showVerify(Request $request)
    {
        $phone = $request->session()->get('admin_phone_reset.phone');

        if (! $phone) {
            return $this->restart();
        }

        return view('admin.auth.phone-verify', [
            'maskedPhone' => $this->phones->mask($phone),
            'resendIn' => $this->otps->secondsUntilCanSend($phone, self::PURPOSE),
        ]);
    }

    public function verifyCode(VerifyPhoneCodeRequest $request)
    {
        $phone = $request->session()->get('admin_phone_reset.phone');

        if (! $phone) {
            return $this->restart();
        }

        $otp = $this->otps->verify($phone, self::PURPOSE, $request->validated('code'));

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => $this->otps->attemptsExhausted($phone, self::PURPOSE)
                    ? 'Too many incorrect attempts. Please request a new code.'
                    : 'That code is incorrect or has expired.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget(['admin_phone_reset.phone', 'admin_phone_reset.email']);
        $request->session()->put('admin_phone_reset.otp_id', $otp->id);

        return redirect()->route('admin.password.phone.reset');
    }

    public function showReset(Request $request)
    {
        if (! $request->session()->has('admin_phone_reset.otp_id')) {
            return $this->restart();
        }

        return view('admin.auth.phone-reset-password');
    }

    public function reset(PhoneResetPasswordRequest $request, AuditLogService $auditLog)
    {
        $otpId = $request->session()->get('admin_phone_reset.otp_id');

        if (! $otpId) {
            return $this->restart();
        }

        $admin = DB::transaction(function () use ($otpId, $request) {
            $otp = $this->otps->consumeVerified((int) $otpId, self::PURPOSE);
            $admin = $otp?->admin;

            // Still active, and the code went to the number still verified on the account.
            if (! $admin || ! $admin->is_active || ! $admin->hasVerifiedPhone() || $admin->phone_e164 !== $otp->phone) {
                return null;
            }

            $admin->forceFill([
                'password' => $request->validated('password'),
                'remember_token' => Str::random(60),
            ])->save();

            $this->otps->invalidateForAdmin($admin, self::PURPOSE);

            return $admin;
        });

        $request->session()->forget('admin_phone_reset');

        if (! $admin) {
            return $this->restart('Your reset session has expired. Please request a new code.');
        }

        // Any outstanding emailed reset link for this account is now stale too.
        Password::broker('admins')->deleteToken($admin);
        event(new PasswordReset($admin));

        $auditLog->log('admin.password_reset', 'admin', $admin->id, "Staff member {$admin->email} reset their password using an SMS code.");

        return redirect()->route('admin.login')->with('status', 'Your password has been reset. You can now sign in.');
    }

    protected function requestCode(Request $request, string $phone, string $email)
    {
        // Same answer for everyone, before any account lookup.
        if (! $this->otps->smsAvailable()) {
            throw ValidationException::withMessages(['phone' => 'Password reset by SMS is not available right now. Please use the emailed reset link instead.']);
        }

        if ($wait = $this->otps->secondsUntilCanSend($phone, self::PURPOSE)) {
            $message = "Please wait {$wait} seconds before requesting another code.";

            if ($request->routeIs('admin.password.phone.resend')) {
                return redirect()->route('admin.password.phone.verify')->withErrors(['code' => $message]);
            }

            throw ValidationException::withMessages(['phone' => $message]);
        }

        $this->otps->recordSendRequest($phone, self::PURPOSE);

        $admin = Admin::where('phone_e164', $phone)
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->whereNotNull('phone_verified_at')
            ->where('is_active', true)
            ->first();

        if ($admin) {
            try {
                $this->otps->issue($admin, $phone, self::PURPOSE, deferDelivery: true);
            } catch (SmsException) {
                // Already logged; the response must not differ.
            }
        }

        $request->session()->forget('admin_phone_reset.otp_id');
        $request->session()->put('admin_phone_reset.phone', $phone);
        $request->session()->put('admin_phone_reset.email', $email);

        return redirect()->route('admin.password.phone.verify')
            ->with('status', 'If an active staff account uses this email and verified phone number, we have sent a 6-digit code to the phone.');
    }

    protected function restart(?string $error = null)
    {
        $redirect = redirect()->route('admin.password.request');

        return $error ? $redirect->withErrors(['code' => $error]) : $redirect;
    }
}
