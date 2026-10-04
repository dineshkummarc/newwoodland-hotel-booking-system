<?php

namespace App\Http\Controllers;

use App\Contracts\SmsSenderInterface;
use App\Http\Requests\Account\SendPhoneVerificationRequest;
use App\Http\Requests\Auth\VerifyPhoneCodeRequest;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Services\PhoneNumberService;
use App\Services\PhoneOtpService;
use App\Services\Sms\SmsException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lets a signed-in customer prove they own a phone number. Only a number
 * verified here (users.phone_e164) can later be used for SMS password
 * recovery. The pending number lives in the session ("phone_verify.phone")
 * between sending and verifying the code.
 */
class AccountPhoneController extends Controller
{
    private const PURPOSE = PhoneOtp::PURPOSE_PHONE_VERIFICATION;

    public function __construct(
        protected PhoneOtpService $otps,
        protected PhoneNumberService $phones,
    ) {}

    public function edit(Request $request)
    {
        $user = $request->user();
        $pending = $request->session()->get('phone_verify.phone');
        $sentAt = (int) $request->session()->get('phone_verify.sent_at', 0);

        // Pre-fill from the contact number given at registration.
        $current = $user->phone_e164 ?: $this->phones->toE164($user->phone);

        return view('account.phone', [
            'user' => $user,
            'verifiedPhone' => $user->phone_verified_at && $user->phone_e164
                ? $this->phones->formatInternational($user->phone_e164)
                : null,
            'prefillCountry' => $current ? $this->phones->regionFor($current) : null,
            'prefillNumber' => $current ? $this->phones->nationalNumber($current) : null,
            'pendingPhone' => $pending ? $this->phones->formatInternational($pending) : null,
            'resendIn' => $pending ? $this->otps->secondsUntilCanSend($pending, self::PURPOSE) : 0,
            'expiresIn' => max(0, $sentAt + config('sms.otp.ttl_minutes') * 60 - now()->timestamp),
        ]);
    }

    public function sendCode(SendPhoneVerificationRequest $request, SmsSenderInterface $sms)
    {
        $user = $request->user();
        $phone = $request->e164();

        if ($user->phone_verified_at && $user->phone_e164 === $phone) {
            throw ValidationException::withMessages(['phone' => 'This number is already verified on your account.']);
        }

        if (User::where('phone_e164', $phone)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => 'This phone number is already linked to another account.']);
        }

        if (! $sms->supports($phone)) {
            throw ValidationException::withMessages(['phone' => "Sorry, we can't send SMS codes to numbers in this country yet."]);
        }

        return $this->deliver($request, $user, $phone, 'phone');
    }

    public function resendCode(Request $request)
    {
        $phone = $request->session()->get('phone_verify.phone');

        if (! $phone) {
            return redirect()->route('account.phone');
        }

        return $this->deliver($request, $request->user(), $phone, 'code');
    }

    public function verifyCode(VerifyPhoneCodeRequest $request)
    {
        $user = $request->user();
        $phone = $request->session()->get('phone_verify.phone');

        if (! $phone) {
            return redirect()->route('account.phone');
        }

        $otp = $this->otps->verify($phone, self::PURPOSE, $request->validated('code'), $user->id, consume: true);

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => $this->otps->attemptsExhausted($phone, self::PURPOSE)
                    ? 'Too many incorrect attempts. Please request a new code.'
                    : 'That code is incorrect or has expired.',
            ]);
        }

        try {
            DB::transaction(function () use ($user, $phone) {
                $user->forceFill([
                    'phone' => $phone,
                    'phone_e164' => $phone,
                    'phone_verified_at' => now(),
                ])->save();

                // A code sent to the previous number must not reset the password.
                $this->otps->invalidateForUser($user, PhoneOtp::PURPOSE_PASSWORD_RESET);
            });
        } catch (UniqueConstraintViolationException) {
            // Another account verified the same number in the meantime.
            $request->session()->forget('phone_verify');

            return redirect()->route('account.phone')
                ->withErrors(['phone' => 'This phone number is already linked to another account.']);
        }

        $request->session()->forget('phone_verify');

        return redirect()->route('account.phone')->with('success', 'Your phone number has been verified. You can now use it to recover your password.');
    }

    protected function deliver(Request $request, User $user, string $phone, string $errorField)
    {
        if (! $this->otps->smsAvailable()) {
            throw ValidationException::withMessages([$errorField => 'SMS codes can\'t be sent at the moment, so your number can\'t be verified right now. Please try again later or contact the hotel.']);
        }

        if ($wait = $this->otps->secondsUntilCanSend($phone, self::PURPOSE)) {
            throw ValidationException::withMessages([$errorField => "Please wait {$wait} seconds before requesting another code."]);
        }

        $this->otps->recordSendRequest($phone, self::PURPOSE);

        try {
            $this->otps->issue($user, $phone, self::PURPOSE);
        } catch (SmsException) {
            throw ValidationException::withMessages([$errorField => 'We could not send the SMS right now. Please try again in a few minutes.']);
        }

        $request->session()->put('phone_verify.phone', $phone);
        $request->session()->put('phone_verify.sent_at', now()->timestamp);

        return redirect()->route('account.phone')->with('success', $this->otps->deliversToPhones()
            ? 'We sent a 6-digit code to '.$this->phones->formatInternational($phone).'.'
            : 'A 6-digit code was generated for '.$this->phones->formatInternational($phone).' — development mode: no text message was sent (see the notice below).');
    }
}
