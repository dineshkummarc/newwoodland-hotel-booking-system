@extends('layouts.app')

@section('title', 'Complete Payment | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">One Step Left</span>
            <h1 class="font-heading">Complete Your Payment</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Your room is held for {{ $booking->customer_name }} — choose how you would like to pay to complete your reservation at Newwoodland Hotel & Lounge.
            </p>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="p-4 p-md-5 bg-white rounded-4 shadow-sm border">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    <div class="text-center mb-4">
                        <span class="section-subtitle">Booking Reference</span>
                        <h2 class="font-heading text-emerald">{{ $booking->confirmation_code }}</h2>
                        <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }} — awaiting payment</span>
                    </div>

                    <table class="table">
                        <tr><td class="text-muted">Room</td><td class="text-end fw-semibold">{{ $booking->roomNames() }}</td></tr>
                        <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->checkInLabel() }}</td></tr>
                        <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->checkOutLabel() }}</td></tr>
                        <tr><td class="text-muted">Nights</td><td class="text-end fw-semibold">{{ $booking->nights }}</td></tr>
                        <tr><td class="text-muted">Guests</td><td class="text-end fw-semibold">{{ $booking->guests_label }}</td></tr>
                        <tr><td class="text-muted">Nightly Rate</td><td class="text-end fw-semibold">{{ npr($booking->nightly_rate) }}</td></tr>
                        @if($booking->hasDiscount())
                        <tr><td class="text-muted">Subtotal</td><td class="text-end fw-semibold">{{ npr($booking->subtotal()) }}</td></tr>
                        <tr><td class="text-success">First-booking discount ({{ percent($booking->discount_percent) }})</td><td class="text-end fw-semibold text-success">− {{ npr($booking->discount_amount) }}</td></tr>
                        @endif
                        <tr><td class="text-muted fs-5">Total Amount</td><td class="text-end fw-bold fs-5 text-emerald">{{ npr($booking->total_price) }}</td></tr>
                    </table>

                    @php
                        // $paymentMethods only contains methods the hotel accepts right now.
                        $selectedMethod = old('payment_method', array_key_first($paymentMethods));
                        $methodColumns = 'col-12 col-sm-6'.(count($paymentMethods) > 2 ? ' col-md-4' : '');
                    @endphp

                    <form id="payment-method-form" action="{{ route('booking.pay.continue', $booking->confirmation_code) }}" method="POST" class="mt-4" novalidate>
                        @csrf
                        <fieldset>
                        <legend class="section-subtitle d-block mb-3 fs-6">Choose a Payment Method</legend>

                        @error('payment_method')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror

                        <div class="row g-3">
                            @foreach($paymentMethods as $key => $method)
                            <div class="{{ $methodColumns }}">
                                <label class="payment-method-card payment-method-card--active payment-method-option w-100 h-100 mb-0">
                                    <input type="radio" name="payment_method" value="{{ $key }}"
                                           class="payment-method-option__input"
                                           data-method-label="{{ $method['label'] }}"
                                           data-method-description="{{ $method['description'] }}"
                                           @checked($selectedMethod === $key)>
                                    <i class="bi {{ $method['icon'] }} fs-2"></i>
                                    <span class="payment-method-card__label">{{ $method['label'] }}</span>
                                    @if($key === 'bank_transfer' && $bankName)
                                        <span class="payment-method-card__status text-muted">{{ $bankName }}</span>
                                    @elseif($key === 'cash')
                                        <span class="payment-method-card__status text-muted">Pay at Hotel</span>
                                    @else
                                        <span class="payment-method-card__status text-success"><i class="bi bi-check-circle-fill"></i> Available</span>
                                    @endif
                                </label>
                            </div>
                            @endforeach
                        </div>
                        </fieldset>

                        @if($paymentMethods)
                            <p id="payment-method-description" class="text-muted small text-center mt-3 mb-0">
                                {{ $paymentMethods[$selectedMethod]['description'] ?? '' }}
                            </p>

                            @if($split)
                            <fieldset id="payment-plan" class="mt-4" data-split-methods="{{ implode(',', $split['methods']) }}">
                                <legend class="section-subtitle d-block mb-3 fs-6">How Much to Pay Now</legend>
                                <div class="row g-3">
                                    <div class="col-12 col-sm-6">
                                        <label class="payment-method-card payment-method-card--active payment-method-option w-100 h-100 mb-0">
                                            <input type="radio" name="payment_plan" value="full" class="payment-method-option__input" @checked(old('payment_plan', 'full') === 'full')>
                                            <span class="payment-method-card__label">Pay 100% Now</span>
                                            <span class="payment-method-card__status text-muted">{{ npr($booking->total_price) }} now</span>
                                        </label>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="payment-method-card payment-method-card--active payment-method-option w-100 h-100 mb-0">
                                            <input type="radio" name="payment_plan" value="split" class="payment-method-option__input" @checked(old('payment_plan') === 'split')>
                                            <span class="payment-method-card__label">Pay {{ $split['percent'] }}% Now + {{ 100 - $split['percent'] }}% at Hotel</span>
                                            <span class="payment-method-card__status text-muted">{{ npr($split['now']) }} now, {{ npr($split['later']) }} — Pay at Hotel</span>
                                        </label>
                                    </div>
                                </div>
                            </fieldset>
                            @endif
                            <button type="submit" id="payment-continue" class="btn-gold w-100 justify-content-center py-3 mt-4">
                                <i class="bi bi-shield-lock"></i>
                                <span id="payment-continue-text">Continue Payment — {{ npr($booking->total_price) }}</span>
                            </button>
                        @else
                            <div class="alert alert-warning mt-4 mb-0">No payment method is available right now. Please contact the hotel to complete your booking.</div>
                        @endif
                    </form>

                    {{-- Guests from outside Nepal often have no eSewa/Fonepay or Nepali bank app. --}}
                    <p class="small text-muted mt-4 mb-0">
                        <i class="bi bi-globe2 me-1"></i>
                        Visiting from outside Nepal, or can't use these payment methods? Please contact the hotel
                        @if($hotelPhone ?? null) on <a href="tel:{{ $hotelPhoneRaw }}">{{ $hotelPhone }}</a>@endif
                        @if(($hotelPhone ?? null) && ($hotelEmail ?? null)) or @endif
                        @if($hotelEmail ?? null) at <a href="mailto:{{ $hotelEmail }}">{{ $hotelEmail }}</a>@endif
                        with your booking reference <strong>{{ $booking->confirmation_code }}</strong> and we will help you arrange payment.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script>
    // Explain what the selected method means,
    // and offer "pay part now" only for methods that support it.
    (function () {
        var plan = document.getElementById('payment-plan');
        var splitMethods = plan ? plan.getAttribute('data-split-methods').split(',') : [];

        function sync(input) {
            var description = document.getElementById('payment-method-description');
            if (description) { description.textContent = input.getAttribute('data-method-description') || ''; }
            if (plan) {
                var supported = splitMethods.indexOf(input.value) !== -1;
                plan.hidden = !supported;
                if (!supported) { plan.querySelector('input[value="full"]').checked = true; }
            }
        }

        document.querySelectorAll('input[name="payment_method"]').forEach(function (input) {
            input.addEventListener('change', function () { sync(input); });
            if (input.checked) { sync(input); }
        });
    })();

    // Processing state: once "Continue Payment" is pressed, lock the form so
    // a double-click can't start two payments, and show that it's processing.
    (function () {
        var form = document.getElementById('payment-method-form');
        var button = document.getElementById('payment-continue');
        var text = document.getElementById('payment-continue-text');
        if (!form || !button || !text) { return; }
        var originalText = text.textContent;

        form.addEventListener('submit', function (event) {
            var chosen = form.querySelector('input[name="payment_method"]:checked');
            if (!chosen || form.dataset.submitting) {
                if (!chosen) { event.preventDefault(); }
                if (form.dataset.submitting) { event.preventDefault(); }
                return;
            }
            form.dataset.submitting = '1';
            text.textContent = 'Processing…';
            button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>');
            // Disable after the submit event has been dispatched, so the POST still goes out.
            setTimeout(function () {
                button.disabled = true;
                form.querySelectorAll('input[name="payment_method"]').forEach(function (input) { input.disabled = true; });
            }, 0);
        });

        // If the guest hits Back after being redirected to a gateway, some
        // browsers restore this page from cache (bfcache) still locked on
        // "Redirecting…" — reset it so the page is usable without a refresh.
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) { return; }
            delete form.dataset.submitting;
            button.disabled = false;
            text.textContent = originalText;
            var spinner = button.querySelector('.spinner-border');
            if (spinner) { spinner.remove(); }
            form.querySelectorAll('input[name="payment_method"]').forEach(function (input) { input.disabled = false; });
        });
    })();
</script>
@endpush
@endsection
