@extends('layouts.app')

@section('title', 'Forgot Password | New Woodland Hotel')
@section('meta_robots', 'noindex, follow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Forgot Password</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Enter your email address and we'll send you a link to reset your password.
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

                    <form action="{{ route('password.email') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="booking-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                        </div>
                        <button type="submit" class="btn-gold w-100 justify-content-center py-3">Send Reset Link</button>
                    </form>

                    <p class="text-center text-muted small mt-4 mb-0">
                        <a href="{{ route('login') }}" class="text-emerald fw-semibold">Back to Sign In</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
