@extends('admin.auth._layout')

@section('title', 'Reset Staff Password')

@section('content')
<p class="small text-muted">Enter your staff email address and we'll email you a link to choose a new password.</p>
<form action="{{ route('admin.password.email') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="admin-forgot-email" class="form-label">Email Address</label>
        <input id="admin-forgot-email" type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
    </div>
    <button type="submit" class="btn-admin-primary w-100">Email Reset Link</button>
</form>

<hr class="my-4">

<details @if($errors->hasAny(['phone', 'phone_country']) || old('recovery_method') === 'phone') open @endif>
    <summary class="small fw-semibold">Or reset with an SMS code</summary>
    <div class="mt-3">
        <p class="small text-muted">
            Only works if you verified your phone number in <em>Admin → Profile</em>. Enter your staff email
            <strong>and</strong> that phone number; we'll text a 6-digit code to the phone.
        </p>
        @include('partials.sms-status-notice', ['emailAlternative' => 'Please use the emailed reset link above instead.'])
        <form action="{{ route('admin.password.phone.send') }}" method="POST" novalidate>
            @csrf
            <input type="hidden" name="recovery_method" value="phone">
            <div class="mb-3">
                <label for="admin-phone-reset-email" class="form-label">Staff Email Address</label>
                <input id="admin-phone-reset-email" type="email" name="email" class="form-control" value="{{ old('email') }}" required autocomplete="username">
            </div>
            @include('partials.phone-input', ['id' => 'admin-reset-phone', 'label' => 'Verified Phone Number'])
            <button type="submit" class="btn-admin-primary w-100">Send Code</button>
        </form>
    </div>
</details>
@endsection
