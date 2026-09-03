@extends('layouts.app')

@section('title', 'Sign In | New Woodland Hotel')
@section('meta_robots', 'noindex, follow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Welcome Back</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Sign in to view your bookings.
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

                    <form action="{{ route('login.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="booking-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="booking-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                                <label class="form-check-label text-muted small" for="remember">Remember me</label>
                            </div>
                            <a href="{{ route('password.request') }}" class="text-emerald small fw-semibold">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn-gold w-100 justify-content-center py-3">Sign In</button>
                    </form>

                    <p class="text-center text-muted small mt-4 mb-0">
                        Don't have an account? <a href="{{ route('register') }}" class="text-emerald fw-semibold">Create one</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
