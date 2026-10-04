@extends('layouts.app')

@section('title', 'Forgot Password | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, follow')

@php
    $usePhone = request('method') === 'phone'
        || old('recovery_method') === 'phone'
        || $errors->hasAny(['phone', 'phone_country']);
@endphp

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Forgot Password</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Recover your account using your email address or your verified phone number.
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

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <ul class="nav nav-pills recovery-tabs mb-4" role="tablist">
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link w-100 {{ $usePhone ? '' : 'active' }}" id="recover-email-tab"
                                    data-bs-toggle="pill" data-bs-target="#recover-email" type="button" role="tab"
                                    aria-controls="recover-email" aria-selected="{{ $usePhone ? 'false' : 'true' }}">
                                <i class="bi bi-envelope me-1"></i> Email
                            </button>
                        </li>
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link w-100 {{ $usePhone ? 'active' : '' }}" id="recover-phone-tab"
                                    data-bs-toggle="pill" data-bs-target="#recover-phone" type="button" role="tab"
                                    aria-controls="recover-phone" aria-selected="{{ $usePhone ? 'true' : 'false' }}">
                                <i class="bi bi-phone me-1"></i> Phone Number
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade {{ $usePhone ? '' : 'show active' }}" id="recover-email" role="tabpanel" aria-labelledby="recover-email-tab">
                            <p class="text-muted small mb-3">Enter your email address and we'll send you a link to reset your password.</p>
                            <form action="{{ route('password.email') }}" method="POST">
                                @csrf
                                <div class="mb-4">
                                    <label for="forgot-email" class="booking-label">Email Address *</label>
                                    <input id="forgot-email" type="email" name="email" class="form-control" value="{{ old('email') }}" required @unless($usePhone) autofocus @endunless>
                                </div>
                                <button type="submit" class="btn-gold w-100 justify-content-center py-3">Send Reset Link</button>
                            </form>
                        </div>

                        <div class="tab-pane fade {{ $usePhone ? 'show active' : '' }}" id="recover-phone" role="tabpanel" aria-labelledby="recover-phone-tab">
                            <p class="text-muted small mb-3">
                                Enter the phone number you verified on your account and we'll text you a 6-digit code.
                            </p>
                            @include('partials.sms-status-notice', ['emailAlternative' => 'Please use the Email Address option instead.'])
                            <form action="{{ route('password.phone.send') }}" method="POST" data-loading-text="Sending code…" novalidate>
                                @csrf
                                <input type="hidden" name="recovery_method" value="phone">
                                @include('partials.phone-input', ['id' => 'forgot-phone', 'label' => 'Phone Number'])
                                <button type="submit" class="btn-gold w-100 justify-content-center py-3 mt-2">Send Code</button>
                            </form>
                            <p class="text-muted small mt-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Only <strong>verified</strong> numbers can be used — a number entered when you registered
                                is not verified until you confirm it with a code at <em>My Account → Phone Number</em>.
                                SMS codes can only be sent to Nepali mobile numbers; guests with other numbers can use email.
                            </p>
                        </div>
                    </div>

                    <p class="text-center text-muted small mt-4 mb-0">
                        <a href="{{ route('login') }}" class="text-emerald fw-semibold">Back to Sign In</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
