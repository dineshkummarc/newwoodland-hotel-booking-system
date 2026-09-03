@extends('layouts.app')

@section('title', 'Reset Password | New Woodland Hotel')
@section('meta_robots', 'noindex, follow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">Reset Password</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Choose a new password for your account.
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

                    <form action="{{ route('password.update') }}" method="POST">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="mb-3">
                            <label class="booking-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="booking-label">New Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="booking-label">Confirm New Password *</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                        <button type="submit" class="btn-gold w-100 justify-content-center py-3">Reset Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
