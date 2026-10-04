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
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <div><span class="status confirmed">{{ $admin->roleLabel() }}</span></div>
                    <small class="text-muted">Roles are assigned by a super admin and cannot be changed here.</small>
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
    <div class="col-lg-6">
        <div class="admin-panel" id="recovery-phone">
            <h2 class="mb-3">Phone for Password Recovery</h2>
            @include('partials.sms-status-notice', ['emailAlternative' => 'You can still reset your password with an emailed link.'])
            <p class="small text-muted mb-3">
                @if($admin->hasVerifiedPhone())
                    Verified: <strong>{{ app(\App\Services\PhoneNumberService::class)->formatInternational($admin->phone_e164) }}</strong>.
                    You can reset your password with an SMS code sent to this number (your staff email is also required).
                @else
                    No verified phone yet. Verify one to be able to reset your password by SMS code as well as by email.
                @endif
            </p>

            @if(session('admin_phone_verify.phone'))
                <form action="{{ route('admin.profile.phone.verify') }}" method="POST" class="mb-2">
                    @csrf
                    <label for="admin-phone-code" class="form-label">Code sent to {{ app(\App\Services\PhoneNumberService::class)->mask(session('admin_phone_verify.phone')) }}</label>
                    <div class="d-flex gap-2">
                        <input id="admin-phone-code" type="text" name="code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                        <button type="submit" class="btn-admin-primary">Verify</button>
                    </div>
                </form>
                <form action="{{ route('admin.profile.phone.resend') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm px-0">Resend code</button>
                </form>
                <hr>
            @endif

            <form action="{{ route('admin.profile.phone.send') }}" method="POST" novalidate>
                @csrf
                @include('partials.phone-input', ['id' => 'admin-profile-phone', 'label' => $admin->hasVerifiedPhone() ? 'Change to a new number' : 'Phone Number', 'required' => true, 'help' => 'Nepali mobile numbers only (SMS is sent via Sparrow).'])
                <div class="mb-3">
                    <label for="admin-phone-current-password" class="form-label">Current Password *</label>
                    <input id="admin-phone-current-password" type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-admin-primary">Send Verification Code</button>
            </form>
        </div>
    </div>
</div>
@endsection
