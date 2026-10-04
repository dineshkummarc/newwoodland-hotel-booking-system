{{--
    Honest SMS status for OTP pages. Depends only on the server's SMS setup
    (never on any account), so it reveals nothing about who is registered.
    - SMS not set up (e.g. SMS_DRIVER=sparrow without credentials): codes
      cannot be sent — say so instead of pretending.
    - Local "log" driver: codes are written to the log file, not texted.
--}}
@inject('smsOtps', 'App\Services\PhoneOtpService')
@if(! $smsOtps->smsAvailable())
    <div class="alert alert-warning small" role="status">
        <i class="bi bi-exclamation-triangle me-1"></i>
        SMS codes can't be sent at the moment. {{ $emailAlternative ?? 'Please use your email address instead, or contact the hotel.' }}
    </div>
@elseif(! $smsOtps->deliversToPhones() && ! app()->isProduction())
    <div class="alert alert-info small" role="status">
        <i class="bi bi-tools me-1"></i>
        <strong>Development mode:</strong> SMS is not connected to a provider (<code>SMS_DRIVER={{ config('sms.driver') }}</code>),
        so <strong>no text message is sent</strong>. For local testing, the code is written to <code>storage/logs/laravel.log</code>.
        Set <code>SMS_DRIVER=sparrow</code> with Sparrow credentials to send real SMS.
    </div>
@endif
