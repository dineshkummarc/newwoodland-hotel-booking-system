@extends('layouts.app')

@section('title', 'View Your Booking | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

{{--
    Shown whenever a booking page is opened without access (see
    App\Support\BookingAccess). Deliberately shows NOTHING about any
    booking — the same page for unknown codes and for real ones.
--}}
@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Your Booking</span>
            <h1 class="font-heading">View Your Booking</h1>
        </div>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="p-4 p-md-5 bg-light rounded-4 shadow-sm border">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <p>For your privacy, booking details are only shown to the guest who made the booking.</p>
                    <ul class="small text-muted">
                        <li>Open the <strong>“View my booking”</strong> link in the email we sent you, or</li>
                        <li>@auth go to <a href="{{ route('account.bookings') }}">My Bookings</a> @else <a href="{{ route('login') }}">sign in</a> if you booked with an account @endauth, or</li>
                        <li>enter your booking reference and email below, and we will email you a secure link.</li>
                    </ul>

                    <form method="POST" action="{{ route('booking.access-link') }}" class="mt-3">
                        @csrf
                        <div class="mb-3">
                            <label for="access-code" class="booking-label">Booking Reference</label>
                            <input id="access-code" type="text" name="confirmation_code" class="form-control @error('confirmation_code') is-invalid @enderror"
                                   value="{{ old('confirmation_code') }}"
                                   placeholder="NW-2026-XXXXXX" maxlength="40" autocomplete="off" required>
                            @error('confirmation_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="access-email" class="booking-label">Email Address used for the booking</label>
                            <input id="access-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn-gold">Email me a link</button>
                    </form>

                    @if(($hotelPhone ?? null) || ($hotelEmail ?? null))
                    <p class="small text-muted mt-4 mb-0">
                        No access to that email? Contact the hotel
                        @if($hotelPhone ?? null) on <a href="tel:{{ $hotelPhoneRaw }}">{{ $hotelPhone }}</a>@endif
                        @if(($hotelPhone ?? null) && ($hotelEmail ?? null)) or @endif
                        @if($hotelEmail ?? null) at <a href="mailto:{{ $hotelEmail }}">{{ $hotelEmail }}</a>@endif
                        with your booking reference.
                    </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
