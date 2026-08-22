@extends('layouts.admin')

@section('title', 'Profile')

@section('content')
<div class="row g-4">
    <div class="col-lg-6">
        <div class="admin-panel">
            <h2 class="mb-3">Profile Information</h2>
            <form action="{{ route('admin.profile.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $admin->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $admin->email) }}" required>
                </div>
                <button type="submit" class="btn-admin-primary">Update Profile</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-panel">
            <h2 class="mb-3">Change Password</h2>
            <form action="{{ route('admin.profile.password') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Current Password *</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password *</label>
                    <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn-admin-gold">Change Password</button>
            </form>
        </div>
    </div>
</div>
@endsection
