<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SmsSenderInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPhoneResetCodeRequest;
use App\Http\Requests\Auth\VerifyPhoneCodeRequest;
use App\Models\Admin;
use App\Models\PhoneOtp;
use App\Services\AuditLogService;
use App\Services\PhoneNumberService;
use App\Services\PhoneOtpService;
use App\Services\Sms\SmsException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A staff member proves they own a phone number (Admin → Profile), so it
 * can later be used to reset their password by SMS code. Same OTP rules as
 * customers (PhoneOtpService): hashed single-use codes, 5-minute expiry,
 * attempt limits, resend cooldown. Changing the number needs the current
 * password, because the number can reset the password.
 */
class ProfilePhoneController extends Controller
{
    private const PURPOSE = PhoneOtp::PURPOSE_ADMIN_PHONE_VERIFICATION;

    public function __construct(
        protected PhoneOtpService $otps,
        protected PhoneNumberService $phones,
    ) {}

    public function sendCode(SendPhoneResetCodeRequest $request, SmsSenderInterface $sms)
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $request->validate(['current_password' => ['required', 'current_password:admin']], [
            'current_password.current_password' => 'Your current password is incorrect.',
        ]);

        $phone = $request->e164();

        if ($admin->hasVerifiedPhone() && $admin->phone_e164 === $phone) {
            throw ValidationException::withMessages(['phone' => 'This number is already verified on your account.']);
        }

        if (Admin::where('phone_e164', $phone)->whereKeyNot($admin->id)->exists()) {
            throw ValidationException::withMessages(['phone' => 'This phone number is already linked to another staff account.']);
        }

        if (! $sms->supports($phone)) {
            throw ValidationException::withMessages(['phone' => "Sorry, SMS codes can't be sent to numbers in this country."]);
        }

        return $this->deliver($request, $admin, $phone);
    }

    public function resendCode(Request $request)
    {
        $phone = $request->session()->get('admin_phone_verify.phone');

        if (! $phone) {
            return redirect()->route('admin.profile.edit');
        }

        return $this->deliver($request, Auth::guard('admin')->user(), $phone);
    }

    public function verifyCode(VerifyPhoneCodeRequest $request, AuditLogService $auditLog)
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $phone = $request->session()->get('admin_phone_verify.phone');

        if (! $phone) {
            return redirect()->route('admin.profile.edit');
        }

        $otp = $this->otps->verify($phone, self::PURPOSE, $request->validated('code'), consume: true, adminId: $admin->id);

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => $this->otps->attemptsExhausted($phone, self::PURPOSE)
                    ? 'Too many incorrect attempts. Please request a new code.'
                    : 'That code is incorrect or has expired.',
            ]);
        }

        try {
            DB::transaction(function () use ($admin, $phone) {
                $admin->forceFill(['phone_e164' => $phone, 'phone_verified_at' => now()])->save();

                // A code sent to the previous number must not reset the password.
                $this->otps->invalidateForAdmin($admin, PhoneOtp::PURPOSE_ADMIN_PASSWORD_RESET);
            });
        } catch (UniqueConstraintViolationException) {
            $request->session()->forget('admin_phone_verify');

            return redirect()->route('admin.profile.edit')
                ->withErrors(['phone' => 'This phone number is already linked to another staff account.']);
        }

        $request->session()->forget('admin_phone_verify');
        $auditLog->log('admin.phone_verified', 'admin', $admin->id, "Staff member {$admin->email} verified a phone number for password recovery.", [
            'phone' => $this->phones->mask($phone),
        ]);

        return redirect()->route('admin.profile.edit')->with('success', 'Your phone number is verified. You can now reset your password by SMS code.');
    }

    protected function deliver(Request $request, Admin $admin, string $phone)
    {
        if (! $this->otps->smsAvailable()) {
            throw ValidationException::withMessages(['phone' => 'SMS codes can\'t be sent at the moment (SMS is not set up on this server).']);
        }

        if ($wait = $this->otps->secondsUntilCanSend($phone, self::PURPOSE)) {
            throw ValidationException::withMessages(['phone' => "Please wait {$wait} seconds before requesting another code."]);
        }

        $this->otps->recordSendRequest($phone, self::PURPOSE);

        try {
            $this->otps->issue($admin, $phone, self::PURPOSE);
        } catch (SmsException) {
            throw ValidationException::withMessages(['phone' => 'We could not send the SMS right now. Please try again in a few minutes.']);
        }

        $request->session()->put('admin_phone_verify.phone', $phone);

        return redirect()->route('admin.profile.edit')->with('success', $this->otps->deliversToPhones()
            ? 'We sent a 6-digit code to '.$this->phones->formatInternational($phone).'.'
            : 'A 6-digit code was generated for '.$this->phones->formatInternational($phone).' — development mode: no text message was sent (the code is in storage/logs/laravel.log).');
    }
}
