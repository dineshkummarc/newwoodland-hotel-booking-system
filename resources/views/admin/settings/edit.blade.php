@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div>
                <label class="form-label">Phone Number *</label>
                <input type="text" name="site_phone" class="form-control" value="{{ old('site_phone', $setting->site_phone) }}" required>
            </div>
            <div>
                <label class="form-label">Email Address *</label>
                <input type="email" name="site_email" class="form-control" value="{{ old('site_email', $setting->site_email) }}" required>
            </div>
            <div class="full-width">
                <label class="form-label">Address *</label>
                <input type="text" name="site_address" class="form-control" value="{{ old('site_address', $setting->site_address) }}" required>
            </div>
            <div>
                <label class="form-label">Facebook URL</label>
                <input type="url" name="facebook_url" class="form-control" value="{{ old('facebook_url', $setting->facebook_url) }}">
            </div>
            <div>
                <label class="form-label">Instagram URL</label>
                <input type="url" name="instagram_url" class="form-control" value="{{ old('instagram_url', $setting->instagram_url) }}">
            </div>
            <div>
                <label class="form-label">YouTube URL</label>
                <input type="url" name="youtube_url" class="form-control" value="{{ old('youtube_url', $setting->youtube_url) }}">
            </div>
            <div>
                <label class="form-label">TikTok URL</label>
                <input type="url" name="tiktok_url" class="form-control" value="{{ old('tiktok_url', $setting->tiktok_url) }}">
            </div>
            <div>
                <label class="form-label">Check-in Time *</label>
                <input type="time" name="checkin_time" class="form-control" value="{{ old('checkin_time', $setting->checkin_time ? \Illuminate\Support\Carbon::parse($setting->checkin_time)->format('H:i') : '') }}" required>
            </div>
            <div>
                <label class="form-label">Check-out Time *</label>
                <input type="time" name="checkout_time" class="form-control" value="{{ old('checkout_time', $setting->checkout_time ? \Illuminate\Support\Carbon::parse($setting->checkout_time)->format('H:i') : '') }}" required>
            </div>
            <div>
                <label class="form-label">Cancellation Notice Required (hours) *</label>
                <input type="number" min="0" max="720" name="cancellation_window_hours" class="form-control" value="{{ old('cancellation_window_hours', $setting->cancellation_window_hours ?? 24) }}" required>
                <small class="text-muted">Guests must cancel their own booking at least this many hours before check-in. Applies site-wide.</small>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Save Settings</button>
        </div>
    </form>
</div>
@endsection
