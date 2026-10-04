@extends('layouts.app')

@section('title', 'My Profile | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Profile &amp; Password</h1>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center g-4">
            <div class="col-lg-6">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm border mb-4">
                    <h2 class="h5 font-heading mb-3">Your details</h2>
                    @if($errors->any())
                        <div class="alert alert-danger">
                            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                        </div>
                    @endif
                    <form action="{{ route('account.profile.update') }}" method="POST" novalidate>
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label for="profile-name" class="booking-label">Full Name *</label>
                            <input id="profile-name" type="text" name="name" maxlength="255" required autocomplete="name"
                                   class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}">
                        </div>
                        <div class="mb-3">
                            <label for="profile-email" class="booking-label">Email Address *</label>
                            <input id="profile-email" type="email" name="email" maxlength="255" required autocomplete="email"
                                   class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}">
                            <div class="form-text">Used to sign in and for booking emails. Existing bookings keep the email they were made with.</div>
                        </div>
                        <div class="mb-4">
                            <label for="profile-current-password" class="booking-label">Current Password</label>
                            <input id="profile-current-password" type="password" name="current_password" autocomplete="current-password"
                                   class="form-control @error('current_password') is-invalid @enderror">
                            <div class="form-text">Only needed if you change your email address.</div>
                        </div>
                        <button type="submit" class="btn-gold w-100 justify-content-center py-3">Save Details</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">
                        Phone number: {{ $user->phone ?: 'not set' }} ·
                        <a href="{{ route('account.phone') }}" class="text-emerald fw-semibold">Manage phone number</a>
                    </p>
                </div>

                <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm border">
                    <h2 class="h5 font-heading mb-3">Change password</h2>
                    @if($errors->password->any())
                        <div class="alert alert-danger">
                            @foreach($errors->password->all() as $error)<div>{{ $error }}</div>@endforeach
                        </div>
                    @endif
                    <form action="{{ route('account.password.update') }}" method="POST" novalidate>
                        @csrf @method('PUT')
                        <div class="mb-3">
                            <label for="password-current" class="booking-label">Current Password *</label>
                            <input id="password-current" type="password" name="current_password" required autocomplete="current-password"
                                   class="form-control {{ $errors->password->has('current_password') ? 'is-invalid' : '' }}">
                        </div>
                        <div class="mb-3">
                            <label for="password-new" class="booking-label">New Password *</label>
                            <input id="password-new" type="password" name="password" required minlength="8" autocomplete="new-password"
                                   class="form-control {{ $errors->password->has('password') ? 'is-invalid' : '' }}">
                            <div class="form-text">At least 8 characters.</div>
                        </div>
                        <div class="mb-4">
                            <label for="password-confirm" class="booking-label">Confirm New Password *</label>
                            <input id="password-confirm" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="form-control">
                        </div>
                        <button type="submit" class="btn-gold w-100 justify-content-center py-3">Change Password</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">Changing your password signs you out on your other devices.</p>
                </div>

                <p class="text-center text-muted small mt-4 mb-0">
                    <a href="{{ route('account.bookings') }}" class="text-emerald fw-semibold">Back to My Bookings</a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
