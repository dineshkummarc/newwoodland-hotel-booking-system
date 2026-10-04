@extends('admin.auth._layout')

@section('title', 'Enter SMS Code')

@section('content')
@include('partials.sms-status-notice', ['emailAlternative' => 'Please use the emailed reset link instead.'])
<p class="small text-muted">
    If <strong class="text-nowrap">{{ $maskedPhone }}</strong> is the verified number on an active staff account
    with the email you entered, a 6-digit code is on its way. It expires in {{ config('sms.otp.ttl_minutes') }} minutes.
</p>
<form action="{{ route('admin.password.phone.verify.store') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="admin-otp-code" class="form-label">6-digit code</label>
        <input id="admin-otp-code" type="text" name="code" class="form-control text-center fs-4" inputmode="numeric"
               pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
    </div>
    <button type="submit" class="btn-admin-primary w-100">Verify Code</button>
</form>
<form action="{{ route('admin.password.phone.resend') }}" method="POST" class="text-center mt-3">
    @csrf
    <button type="submit" class="btn btn-link btn-sm" @if($resendIn > 0) disabled @endif>
        {{ $resendIn > 0 ? "Resend available in {$resendIn}s" : 'Resend code' }}
    </button>
</form>
@endsection
