@extends('layouts.app')

@section('title', 'Reset Password | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Reset Password</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Your phone number is verified. Choose a new password for your account.
            </p>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm border">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="alert alert-success small">
                        <i class="bi bi-shield-check me-1"></i>
                        Code verified. Please set your new password within {{ config('sms.otp.reset_window_minutes') }} minutes.
                    </div>

                    <form action="{{ route('password.phone.update') }}" method="POST" data-loading-text="Saving…">
                        @csrf
                        <div class="mb-3">
                            <label for="phone-reset-password" class="booking-label">New Password *</label>
                            <input id="phone-reset-password" type="password" name="password" minlength="8"
                                   class="form-control @error('password') is-invalid @enderror"
                                   autocomplete="new-password" required autofocus>
                            <div class="form-text">At least 8 characters.</div>
                        </div>
                        <div class="mb-4">
                            <label for="phone-reset-password-confirm" class="booking-label">Confirm New Password *</label>
                            <input id="phone-reset-password-confirm" type="password" name="password_confirmation" minlength="8"
                                   class="form-control" autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn-gold w-100 justify-content-center py-3">Reset Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
