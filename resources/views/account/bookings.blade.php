@extends('layouts.app')

@section('title', 'My Bookings | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center">
    <div class="container">
        <div class="hero-content">
            <span class="section-subtitle text-warning">My Account</span>
            <h1 class="font-heading">My Bookings</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Welcome back, {{ auth()->user()->name }}. Here's your reservation history.
            </p>
        </div>
    </div>
</section>

<section class="py-5 bg-light">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @unless(auth()->user()->hasVerifiedPhone())
            <div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span><i class="bi bi-phone me-2 text-emerald"></i>Verify your phone number so you can recover your account by SMS.</span>
                <a href="{{ route('account.phone') }}" class="text-emerald fw-semibold">Verify now</a>
            </div>
        @endunless

        @if($bookings->isEmpty())
            <div class="p-5 bg-white rounded-4 shadow-sm border text-center">
                <p class="text-muted mb-4">You haven't made any bookings yet.</p>
                <a href="{{ route('booking') }}" class="btn-gold">Book a Room</a>
            </div>
        @else
            <div class="row g-4">
                @foreach($bookings as $booking)
                <div class="col-lg-6">
                    <div class="p-4 bg-white rounded-4 shadow-sm border h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="section-subtitle d-block">{{ $booking->confirmation_code }}</span>
                                <h3 class="font-heading h5 mb-0">{{ $booking->roomNames('Room unavailable') }}</h3>
                            </div>
                            <span class="status {{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                        </div>
                        <table class="table table-sm mb-3">
                            <tr><td class="text-muted">Check-in</td><td class="text-end fw-semibold">{{ $booking->checkInLabel() }}</td></tr>
                            <tr><td class="text-muted">Check-out</td><td class="text-end fw-semibold">{{ $booking->checkOutLabel() }}</td></tr>
                            <tr><td class="text-muted">Nights</td><td class="text-end fw-semibold">{{ $booking->nights }}</td></tr>
                            <tr><td class="text-muted">Payment</td><td class="text-end fw-semibold">{{ $booking->isPaid() ? 'Paid' : 'Not paid' }}</td></tr>
                            <tr><td class="text-muted fw-semibold">Total</td><td class="text-end fw-bold text-emerald">{{ npr($booking->total_price) }}</td></tr>
                        </table>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ route('booking.confirmation', $booking->confirmation_code) }}" class="btn-outline py-2 px-4">View Details</a>
                            <a href="{{ route('account.bookings.invoice', $booking->confirmation_code) }}" class="btn-outline py-2 px-4"><i class="bi bi-file-earmark-pdf me-1"></i>Invoice (PDF)</a>
                            @if($booking->isCancellable($cancellationWindowHours))
                                <button type="button" class="btn btn-outline-danger py-2 px-4" data-bs-toggle="modal" data-bs-target="#cancel-modal-{{ $booking->id }}">
                                    Cancel Booking
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                @if($booking->isCancellable($cancellationWindowHours))
                <div class="modal fade" id="cancel-modal-{{ $booking->id }}" tabindex="-1" aria-labelledby="cancel-modal-label-{{ $booking->id }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="cancel-modal-label-{{ $booking->id }}">Cancel Booking {{ $booking->confirmation_code }}?</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to cancel this booking? This cannot be undone.</p>
                                @if($cancellationWindowHours > 0)
                                    <p class="text-muted">Our policy requires cancellation at least {{ $cancellationWindowHours }} hours before check-in.</p>
                                @endif
                                @if($cancellationDeductionPercent ?? null)
                                    <p class="text-muted"><strong>{{ $cancellationDeductionPercent }}% of the booking amount ({{ npr($booking->cancellationCharge($cancellationDeductionPercent)) }}) is deducted on cancellation.</strong></p>
                                @else
                                    <p class="text-muted"><strong>Cancellation before arrival is free — nothing is deducted.</strong></p>
                                @endif
                                @if($booking->amountPaid() > 0)
                                    <p class="text-muted mb-0">A payment of {{ npr($booking->amountPaid()) }} has already been received for this booking. Refunds are not processed automatically — our team will contact you after cancellation to arrange the refund due ({{ npr($booking->refundDue((int) ($cancellationDeductionPercent ?? 0))) }}).</p>
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Booking</button>
                                <form action="{{ route('account.bookings.cancel', $booking->confirmation_code) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Yes, Cancel Booking</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                @endforeach
            </div>

            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
