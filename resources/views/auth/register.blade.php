@extends('layouts.app')

@section('title', 'Create Account | New Woodland Hotel')
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
                            <label class="booking-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="booking-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="booking-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="mb-3">
                            <label class="booking-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="booking-label">Confirm Password *</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
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
