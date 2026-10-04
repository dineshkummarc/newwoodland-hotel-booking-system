@extends('layouts.app')

@section('title', 'Enter Verification Code | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Enter Verification Code</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Check your phone for a text message from us.
            </p>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm border">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @include('partials.sms-status-notice')

                    <p class="text-muted mb-4">
                        If <strong class="text-nowrap">{{ $maskedPhone }}</strong> is the verified number on an account,
                        a 6-digit code is on its way. It can take up to a minute to arrive.
                    </p>

                    @include('partials.otp-code-form', [
                        'verifyAction' => route('password.phone.verify.store'),
                        'resendAction' => route('password.phone.resend'),
                        'resendIn' => $resendIn,
                        'expiresIn' => $expiresIn,
                    ])

                    <p class="text-center text-muted small mt-4 mb-0">
                        <a href="{{ route('password.request', ['method' => 'phone']) }}" class="text-emerald fw-semibold">Use a different number</a>
                        <span class="mx-2">·</span>
                        <a href="{{ route('password.request') }}" class="text-emerald fw-semibold">Recover by email</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
