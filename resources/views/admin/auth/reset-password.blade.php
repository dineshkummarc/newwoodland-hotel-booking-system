@extends('admin.auth._layout')

@section('title', 'Choose a New Password')

@section('content')
<form action="{{ route('admin.password.update') }}" method="POST">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3">
        <label for="admin-reset-email" class="form-label">Email Address</label>
        <input id="admin-reset-email" type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required>
    </div>
    <div class="mb-3">
        <label for="admin-reset-password" class="form-label">New Password</label>
        <input id="admin-reset-password" type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
        <div class="form-text">At least 8 characters.</div>
    </div>
    <div class="mb-3">
        <label for="admin-reset-password-confirm" class="form-label">Confirm New Password</label>
        <input id="admin-reset-password-confirm" type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-admin-primary w-100">Reset Password</button>
</form>
@endsection
