@extends('layouts.app')

@section('title', 'Phone Number | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Phone Number</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Verify your mobile number so you can recover your account by SMS.
            </p>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm border">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @include('partials.sms-status-notice', ['emailAlternative' => 'You can still recover your account by email.'])

                    @if($errors->has('phone') || $errors->has('phone_country') || $errors->has('current_password'))
                        <div class="alert alert-danger">
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <div class="d-flex align-items-center gap-3 p-3 rounded-3 border mb-4">
                        @if($verifiedPhone)
                            <i class="bi bi-patch-check-fill text-success fs-3"></i>
                            <div>
                                <div class="fw-semibold">{{ $verifiedPhone }}</div>
                                <div class="small text-muted">Verified {{ $user->phone_verified_at->format('M j, Y') }} · can be used to reset your password</div>
                            </div>
                        @else
                            <i class="bi bi-exclamation-circle text-warning fs-3"></i>
                            <div>
                                <div class="fw-semibold">No verified phone number</div>
                                <div class="small text-muted">Add one to enable password recovery by SMS.</div>
                            </div>
                        @endif
                    </div>

                    @if($pendingPhone)
                        <h2 class="h5 font-heading mb-2">Enter the code</h2>
                        <p class="text-muted small mb-3">We sent a 6-digit code to <strong class="text-nowrap">{{ $pendingPhone }}</strong>.</p>

                        @include('partials.otp-code-form', [
                            'verifyAction' => route('account.phone.verify'),
                            'resendAction' => route('account.phone.resend'),
                            'resendIn' => $resendIn,
                            'expiresIn' => $expiresIn,
                        ])

                        <hr class="my-4">
                        <p class="small text-muted mb-3">Wrong number? Enter a different one below.</p>
                    @endif

                    <h2 class="h5 font-heading mb-3">{{ $verifiedPhone ? 'Change phone number' : 'Verify a phone number' }}</h2>
                    <form action="{{ route('account.phone.send') }}" method="POST" data-loading-text="Sending code…" novalidate>
                        @csrf
                        @include('partials.phone-input', [
                            'id' => 'account-phone',
                            'label' => 'Mobile Number',
                            'country' => $prefillCountry,
                            'number' => $prefillNumber,
                            'help' => 'We will text a 6-digit code to this number. Standard SMS rates may apply.',
                        ])
                        <div class="mb-4">
                            <label for="account-current-password" class="booking-label">Current Password *</label>
                            <input id="account-current-password" type="password" name="current_password"
                                   class="form-control @error('current_password') is-invalid @enderror"
                                   autocomplete="current-password" required>
                            <div class="form-text">Required to protect your account's recovery settings.</div>
                        </div>
                        <button type="submit" class="btn-gold w-100 justify-content-center py-3">Send Verification Code</button>
                    </form>

                    <p class="text-center text-muted small mt-4 mb-0">
                        <a href="{{ route('account.bookings') }}" class="text-emerald fw-semibold">Back to My Bookings</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
