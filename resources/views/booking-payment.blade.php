@extends('layouts.app')

@section('title', 'Complete Payment | Newwoodland Hotel and Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">One Step Left</span>
            <h1 class="font-heading">Complete Your Payment</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Your room is held for {{ $booking->customer_name }} — complete payment below to confirm your reservation at Newwoodland Hotel and Lounge.
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
                        <tr><td class="text-muted">Room</td><td class="text-end fw-semibold">{{ $booking->room->name }}</td></tr>
                        <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->check_in->format('D, d M Y') }}</td></tr>
                        <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->check_out->format('D, d M Y') }}</td></tr>
                        <tr><td class="text-muted">Nights</td><td class="text-end fw-semibold">{{ $booking->nights }}</td></tr>
                        <tr><td class="text-muted">Guests</td><td class="text-end fw-semibold">{{ $booking->guests_label }}</td></tr>
                        <tr><td class="text-muted">Nightly Rate</td><td class="text-end fw-semibold">{{ npr($booking->nightly_rate) }}</td></tr>
                        <tr><td class="text-muted fs-5">Total Amount</td><td class="text-end fw-bold fs-5 text-emerald">{{ npr($booking->total_price) }}</td></tr>
                    </table>

                    <div class="mt-4">
                        <span class="section-subtitle d-block mb-3">Choose a Payment Method</span>

                        <div class="row g-3">
                            @foreach($paymentMethods as $key => $method)
                            <div class="col-6 col-md-3">
                                @if($method['enabled'])
                                    <form action="{{ $method['initiate_route'] }}" method="POST" class="h-100 m-0">
                                        @csrf
                                        <button type="submit" class="payment-method-card payment-method-card--active w-100 h-100">
                                            <i class="bi {{ $method['icon'] }} fs-2"></i>
                                            <span class="payment-method-card__label">{{ $method['label'] }}</span>
                                            <span class="payment-method-card__status text-success"><i class="bi bi-check-circle-fill"></i> Available</span>
                                        </button>
                                    </form>
                                @else
                                    <button type="button"
                                        class="payment-method-card payment-method-card--disabled w-100 h-100"
                                        data-bs-toggle="modal"
                                        data-bs-target="#payment-unavailable-modal"
                                        data-method-label="{{ $method['label'] }}"
                                        data-method-message="{{ $method['description'] }}">
                                        <i class="bi {{ $method['icon'] }} fs-2"></i>
                                        <span class="payment-method-card__label">{{ $method['label'] }}</span>
                                        <span class="payment-method-card__status text-muted"><i class="bi bi-clock-history"></i> Coming Soon</span>
                                    </button>
                                @endif
                            </div>
                            @endforeach
                        </div>

                        <p class="text-muted small text-center mt-3 mb-0">
                            You will be redirected to your chosen provider's secure payment page. Your booking is confirmed automatically once payment is verified.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="payment-unavailable-modal" tabindex="-1" aria-labelledby="payment-unavailable-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payment-unavailable-title">Payment Method Unavailable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="payment-unavailable-message" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back to Payment Options</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('payment-unavailable-modal')?.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        var title = document.getElementById('payment-unavailable-title');
        var message = document.getElementById('payment-unavailable-message');
        title.textContent = (trigger.getAttribute('data-method-label') || 'Payment Method') + ' — Currently Unavailable';
        message.textContent = trigger.getAttribute('data-method-message') || 'This payment method is not available yet.';
    });
</script>
@endpush
@endsection
