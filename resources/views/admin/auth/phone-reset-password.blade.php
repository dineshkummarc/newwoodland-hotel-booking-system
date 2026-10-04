@extends('admin.auth._layout')

@section('title', 'Choose a New Password')

@section('content')
<form action="{{ route('admin.password.phone.update') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="admin-new-password" class="form-label">New Password</label>
        <input id="admin-new-password" type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required autofocus>
    </div>
    <div class="mb-3">
        <label for="admin-new-password-confirmation" class="form-label">Confirm New Password</label>
        <input id="admin-new-password-confirmation" type="password" name="password_confirmation" class="form-control" minlength="8" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn-admin-primary w-100">Reset Password</button>
</form>
@endsection
