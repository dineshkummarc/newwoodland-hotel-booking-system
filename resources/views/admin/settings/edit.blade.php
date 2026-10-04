@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div>
                <label class="form-label">Hotel Name</label>
                <input type="text" name="hotel_name" class="form-control" value="{{ old('hotel_name', $setting->hotel_name) }}">
            </div>
            <div class="full-width">
                <label class="form-label">Hotel Description</label>
                <textarea name="hotel_description" class="form-control" rows="3">{{ old('hotel_description', $setting->hotel_description) }}</textarea>
            </div>
            <div>
                <label class="form-label">Primary Phone Number *</label>
                <input type="text" name="site_phone" class="form-control" value="{{ old('site_phone', $setting->site_phone) }}" required>
            </div>
            <div>
                <label class="form-label">Secondary Phone Number</label>
                <input type="text" name="site_phone_secondary" class="form-control" value="{{ old('site_phone_secondary', $setting->site_phone_secondary) }}">
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
                <input type="number" min="0" max="720" name="cancellation_window_hours" class="form-control" value="{{ old('cancellation_window_hours', $setting->cancellation_window_hours ?? 0) }}" required>
                <small class="text-muted">Hotel policy: <strong>0</strong> — guests may cancel online any time before arrival (until the end of the check-in day). A higher number requires that much notice before check-in.</small>
            </div>
            <div>
                <label class="form-label">First-Booking Discount (%)</label>
                <input type="number" min="0" max="100" step="0.5" name="first_booking_discount_percent" class="form-control" value="{{ old('first_booking_discount_percent', $setting->first_booking_discount_percent !== null ? (float) $setting->first_booking_discount_percent : null) }}">
                <small class="text-muted">Applied automatically to a guest's first booking; shown on the home page. Blank or 0 = no discount.</small>
            </div>
            <div>
                <label class="form-label">Cancellation Deduction Before Arrival (%)</label>
                <input type="number" min="0" max="100" name="cancellation_deduction_percent" class="form-control" value="{{ old('cancellation_deduction_percent', $setting->cancellation_deduction_percent) }}">
                <small class="text-muted">Hotel policy: <strong>0</strong> — cancellation before arrival is free. Cancelling after check-in always charges {{ config('bookings.after_arrival_cancellation_percent') }}% (Booking → Cancel Stay). Shown in Terms and on cancellation screens; staff see the refund due.</small>
            </div>
            <div class="full-width">
                <label class="form-label">Cancellation Policy (guest-facing text)</label>
                <textarea name="cancellation_policy_text" class="form-control" rows="3">{{ old('cancellation_policy_text', $setting->cancellation_policy_text) }}</textarea>
            </div>
        </div>

        <h2 class="mt-4">Hotel Profile</h2>
        <p class="text-muted small">
            Client-confirmed figures shown on the About page. Leave a field blank to hide it.
            Guest numbers are shown as approximate ("around … guests on a typical day").
        </p>
        <div class="form-grid">
            <div>
                <label class="form-label">Total Rooms</label>
                <input type="number" min="1" name="total_rooms" class="form-control" value="{{ old('total_rooms', $setting->total_rooms) }}">
                <small class="text-muted">The hotel's actual room count. Admin → Rooms shows how many are set up against this.</small>
            </div>
            <div>
                <label class="form-label">Typical Guests per Day</label>
                <input type="text" name="typical_daily_guests" class="form-control" maxlength="50" value="{{ old('typical_daily_guests', $setting->typical_daily_guests) }}" placeholder="e.g. 15–20">
                <small class="text-muted">Approximate figure for a normal (non-weekend) day.</small>
            </div>
            <div>
                <label class="form-label">Guests Served to Date</label>
                <input type="text" name="guest_satisfaction" class="form-control" maxlength="50" value="{{ old('guest_satisfaction', $setting->guest_satisfaction) }}" placeholder="e.g. 90%+">
                <small class="text-muted">Shown on the home and About pages as "… guests served to date".</small>
            </div>
            <div>
                <label class="form-label">Room Price From (NPR / night)</label>
                <input type="number" min="0" step="1" name="room_price_min" class="form-control" value="{{ old('room_price_min', $setting->room_price_min !== null ? (int) $setting->room_price_min : null) }}">
            </div>
            <div>
                <label class="form-label">Room Price Up To (NPR / night)</label>
                <input type="number" min="0" step="1" name="room_price_max" class="form-control" value="{{ old('room_price_max', $setting->room_price_max !== null ? (int) $setting->room_price_max : null) }}">
                <small class="text-muted">The general price range. Each room's exact rate is set on Admin → Rooms.</small>
            </div>
        </div>

        <h2 class="mt-4">Invoice / Tax Details</h2>
        <div class="form-grid">
            <div>
                <label class="form-label">PAN / VAT Number</label>
                <input type="text" name="pan_vat_number" class="form-control" maxlength="50" value="{{ old('pan_vat_number', $setting->pan_vat_number) }}">
                <small class="text-muted">The hotel's registration number as issued. Printed on invoice PDFs only once filled in — leave blank until the hotel confirms it.</small>
            </div>
        </div>

        <h2 class="mt-4">Bank Transfer Details</h2>
        <p class="text-muted small">
            Shown to guests who choose Bank Transfer at checkout. Until an <strong>Account Number</strong> is
            entered here, guests are told the hotel will send them the account details (staff see a reminder
            on the booking). Every field is optional and shown to guests only when filled in.
        </p>
        <div class="form-grid">
            <div>
                <label class="form-label">Bank Name</label>
                <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $setting->bank_name) }}">
            </div>
            <div>
                <label class="form-label">Account Name</label>
                <input type="text" name="bank_account_name" class="form-control" value="{{ old('bank_account_name', $setting->bank_account_name) }}">
            </div>
            <div>
                <label class="form-label">Account Number</label>
                <input type="text" name="bank_account_number" class="form-control" value="{{ old('bank_account_number', $setting->bank_account_number) }}">
            </div>
            <div>
                <label class="form-label">Branch</label>
                <input type="text" name="bank_branch" class="form-control" value="{{ old('bank_branch', $setting->bank_branch) }}">
            </div>
            <div class="full-width">
                <label class="form-label">Payment QR Code (optional)</label>
                @if($setting->bank_qr_path)
                    <div class="mb-2">
                        <img src="{{ asset('storage/'.$setting->bank_qr_path) }}" alt="Current payment QR code" style="max-width: 160px;" class="border rounded p-1 bg-white">
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="remove_bank_qr" value="1" class="form-check-input" id="remove_bank_qr">
                        <label class="form-check-label" for="remove_bank_qr">Remove current QR code</label>
                    </div>
                @endif
                <input type="file" name="bank_qr" class="form-control" accept="image/png,image/jpeg,image/webp">
                <small class="text-muted">Shown on the bank transfer page so guests can scan to pay.</small>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Save Settings</button>
        </div>
    </form>
</div>
@endsection
