@extends('layouts.app')

@section('title', 'Create Account | Newwoodland Hotel and Lounge')
@section('meta_robots', 'noindex, follow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Create Your Account</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Register to track your bookings and speed up future reservations.
            </p>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm border">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('register.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="register-name" class="booking-label">Full Name *</label>
                            <input id="register-name" type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="register-email" class="booking-label">Email Address *</label>
                            <input id="register-email" type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="mb-3">
                            <label for="register-phone" class="booking-label">Phone Number</label>
                            <input id="register-phone" type="tel" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="mb-3">
                            <label for="register-password" class="booking-label">Password *</label>
                            <input id="register-password" type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label for="register-password-confirm" class="booking-label">Confirm Password *</label>
                            <input id="register-password-confirm" type="password" name="password_confirmation" class="form-control" required>
                        </div>
                        <button type="submit" class="btn-gold w-100 justify-content-center py-3">Create Account</button>
                    </form>

                    <p class="text-center text-muted small mt-4 mb-0">
                        Already have an account? <a href="{{ route('login') }}" class="text-emerald fw-semibold">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
