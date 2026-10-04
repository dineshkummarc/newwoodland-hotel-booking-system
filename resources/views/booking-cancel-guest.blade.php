@extends('layouts.app')

@section('title', 'Cancel Booking | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">Cancel Booking</span>
            <h1 class="font-heading">Cancel Your Reservation</h1>
        </div>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="p-4 p-md-5 bg-light rounded-4 shadow-sm border">

                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="text-center mb-4">
                        <span class="section-subtitle">Confirmation Code</span>
                        <h2 class="font-heading text-emerald">{{ $booking->confirmation_code }}</h2>
                        <span class="status {{ $booking->status }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                    </div>

                    <table class="table">
                        <tr><td class="text-muted">Room</td><td class="text-end fw-semibold">{{ $booking->roomNames() }}</td></tr>
                        <tr><td class="text-muted">Guest</td><td class="text-end fw-semibold">{{ $booking->customer_name }}</td></tr>
                        <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->check_in->format('D, d M Y') }}</td></tr>
                        <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->check_out->format('D, d M Y') }}</td></tr>
                        <tr><td class="text-muted">Total Price</td><td class="text-end fw-semibold">{{ npr($booking->total_price) }}</td></tr>
                    </table>

                    @if($booking->status === \App\Models\Booking::STATUS_CANCELLED)
                        <div class="alert alert-secondary mt-3 mb-0">This booking has already been cancelled.</div>
                    @else
                        <p class="text-muted mt-4">
                            Are you sure you want to cancel this booking? This action cannot be undone.
                            {{ $cancellationPolicyText ?? '' }}
                        </p>
                        @if($cancellationDeductionPercent ?? null)
                            <div class="alert alert-warning">
                                As per our cancellation policy, <strong>{{ $cancellationDeductionPercent }}% of the booking amount
                                ({{ npr($booking->cancellationCharge($cancellationDeductionPercent)) }})</strong> is deducted on cancellation.
                                @if($booking->amountPaid() > 0)
                                    You have paid {{ npr($booking->amountPaid()) }}; our team will contact you about any refund due ({{ npr($booking->refundDue($cancellationDeductionPercent)) }}).
                                @endif
                            </div>
                        @else
                            <div class="alert alert-info">
                                <strong>Cancellation before arrival is free</strong> — nothing is deducted.
                                @if($booking->amountPaid() > 0)
                                    You have paid {{ npr($booking->amountPaid()) }}; our team will contact you to refund it in full ({{ npr($booking->refundDue(0)) }}).
                                @endif
                            </div>
                        @endif
                        <form action="{{ url()->full() }}" method="POST" class="d-print-none">
                            @csrf
                            <button type="submit" class="btn btn-danger w-100 py-3">Yes, Cancel This Booking</button>
                        </form>
                    @endif

                    <div class="text-center mt-4">
                        <a href="{{ route('booking.confirmation', $booking->confirmation_code) }}" class="text-muted">Back to booking details</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
