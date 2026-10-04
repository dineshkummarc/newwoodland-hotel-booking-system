{{--
    6-digit code entry with expiry countdown and resend.
    Params: $verifyAction, $resendAction, $resendIn (seconds until resend allowed),
            $expiresIn (seconds until the code expires).
--}}
<form action="{{ $verifyAction }}" method="POST" data-loading-text="Verifying…" novalidate>
    @csrf
    <div class="mb-2">
        <label for="otp-code" class="booking-label">6-digit code *</label>
        <input id="otp-code" type="text" name="code"
               class="form-control otp-input @error('code') is-invalid @enderror"
               inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="7"
               placeholder="••••••" required autofocus>
        @error('code')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <p class="small text-muted mb-4" role="status" aria-live="polite"
       data-countdown="{{ $expiresIn }}"
       data-countdown-template="Code expires in {time}."
       data-countdown-done="This code has expired. Please request a new one.">
        @if($expiresIn > 0)
            Code expires in {{ gmdate('i:s', $expiresIn) }}.
        @else
            This code has expired. Please request a new one.
        @endif
    </p>
    <button type="submit" class="btn-gold w-100 justify-content-center py-3">Verify Code</button>
</form>

<form action="{{ $resendAction }}" method="POST" class="text-center mt-3" data-loading-text="Sending…">
    @csrf
    <button type="submit" id="otp-resend" class="btn btn-link text-emerald fw-semibold small p-0"
            @disabled($resendIn > 0)>Resend code</button>
    <div class="small text-muted mt-1" aria-live="polite"
         data-countdown="{{ $resendIn }}"
         data-countdown-template="You can request a new code in {time}."
         data-countdown-done=""
         data-countdown-enables="otp-resend">
        @if($resendIn > 0)
            You can request a new code in {{ gmdate('i:s', $resendIn) }}.
        @endif
    </div>
</form>
