@extends('layouts.admin')

@section('title', 'Booking ' . $booking->confirmation_code)

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="table-section">
            <h2>Booking Details</h2>
            <table class="table">
                <tr><td class="text-muted">Confirmation Code</td><td><strong>{{ $booking->confirmation_code }}</strong></td></tr>
                <tr><td class="text-muted">{{ $booking->isMultiRoom() ? 'Rooms ('.$booking->roomCount().')' : 'Room' }}</td><td>
                    @foreach($booking->roomLines() as $line)
                        <div>{{ $line['room']?->label() ?? '—' }}@if($booking->isMultiRoom()) <span class="text-muted small">· {{ $line['guests_label'] }} · {{ npr($line['subtotal']) }}</span>@endif</div>
                    @endforeach
                </td></tr>
                <tr><td class="text-muted">Guest</td><td>
                    {{ $booking->customer_name }}
                    @if($guestProfile?->is_vip)<span class="status vip ms-1"><i class="bi bi-star-fill"></i> VIP</span>@endif
                    <a href="{{ route('admin.guests.show', ['email' => $booking->email]) }}" class="small ms-2">Guest profile &amp; notes</a>
                    @if($guestProfile?->notes)
                        <div class="small text-muted mt-1" style="white-space: pre-line">{{ $guestProfile->notes }}</div>
                    @endif
                </td></tr>
                <tr><td class="text-muted">Email</td><td>{{ $booking->email }}</td></tr>
                <tr><td class="text-muted">Phone</td><td>{{ $booking->phone }}</td></tr>
                <tr><td class="text-muted">Country</td><td>{{ $booking->country ?: '—' }}</td></tr>
                <tr><td class="text-muted">Check-in</td><td>{{ $booking->checkInLabel() }}</td></tr>
                <tr><td class="text-muted">Check-out</td><td>{{ $booking->checkOutLabel() }}</td></tr>
                <tr><td class="text-muted">Nights</td><td>{{ $booking->nights }}</td></tr>
                <tr><td class="text-muted">Guests</td><td>{{ $booking->guests_label }}</td></tr>
                @if($booking->hasVariableRates())
                <tr><td class="text-muted">Room Rates</td><td>
                    @foreach($booking->rateLines() as $line)
                        <div>{{ $line['nights'] }} × {{ npr($line['rate']) }} <span class="text-muted small">({{ $line['label'] }})</span></div>
                    @endforeach
                    <div class="text-muted small">Base rate {{ npr($booking->nightly_rate) }} · rates frozen at booking time</div>
                </td></tr>
                @else
                <tr><td class="text-muted">Nightly Rate</td><td>{{ npr($booking->nightly_rate) }}</td></tr>
                @endif
                @if($booking->hasDiscount())
                <tr><td class="text-muted">First-booking Discount</td><td>− {{ npr($booking->discount_amount) }} ({{ percent($booking->discount_percent) }})</td></tr>
                @endif
                <tr><td class="text-muted">Total Price</td><td><strong>{{ npr($booking->total_price) }}</strong></td></tr>
                @php $chargesTotal = $booking->chargesTotal(); @endphp
                @if($chargesTotal > 0)
                <tr><td class="text-muted">Extra Charges</td><td>{{ npr($chargesTotal) }}</td></tr>
                <tr><td class="text-muted">Grand Total</td><td><strong>{{ npr($booking->grandTotal()) }}</strong></td></tr>
                @endif
                <tr><td class="text-muted">Paid / Balance</td><td>{{ npr($booking->amountPaid()) }} paid · <strong>{{ npr(max(0, $booking->outstandingBalance())) }}</strong> due{{ $booking->deposit_percent ? " (guest chose {$booking->deposit_percent}% now, rest at hotel)" : "" }}</td></tr>
                <tr><td class="text-muted">Special Requests</td><td>{{ $booking->special_requests ?: '—' }}</td></tr>
                <tr><td class="text-muted">Booked On</td><td>{{ $booking->created_at->format('d M Y, g:i A') }}</td></tr>
                @if($booking->cancelled_at)
                <tr><td class="text-muted">Cancelled On</td><td>{{ $booking->cancelled_at->format('d M Y, g:i A') }}</td></tr>
                @endif
                @if($booking->checked_in_at)
                <tr><td class="text-muted">Checked In</td><td>{{ $booking->checked_in_at->format('d M Y, g:i A') }}</td></tr>
                @endif
                @if($booking->checked_out_at)
                <tr><td class="text-muted">Checked Out</td><td>{{ $booking->checked_out_at->format('d M Y, g:i A') }}</td></tr>
                @endif
            </table>
        </div>

        @include('admin.bookings._rooms')
        @include('admin.bookings._charges')
        @include('admin.bookings._documents')
    </div>
    <div class="col-lg-4">
        <div class="table-section mb-4">
            <h2>Status</h2>
            <a href="{{ route('admin.bookings.invoice', $booking) }}" class="btn btn-sm btn-outline-dark w-100 mb-3"><i class="bi bi-file-earmark-pdf"></i> Download Invoice (PDF)</a>
            <p><span class="status {{ $booking->status }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span></p>

            @if($booking->status === \App\Models\Booking::STATUS_CONFIRMED)
                <form action="{{ route('admin.bookings.check-in', $booking) }}" method="POST" class="mb-2">
                    @csrf @method('PATCH')
                    @include('admin.bookings._photo-id-check', ['idSuffix' => 'show'])
                    <button type="submit" class="btn-admin-primary w-100">Check In Guest</button>
                </form>
                <form action="{{ route('admin.bookings.no-show', $booking) }}" method="POST" class="mb-3">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-outline-danger w-100">Mark No-Show</button>
                </form>
            @elseif($booking->status === \App\Models\Booking::STATUS_CHECKED_IN)
                <form action="{{ route('admin.bookings.check-out', $booking) }}" method="POST" class="mb-2">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-admin-primary w-100">Check Out Guest</button>
                </form>
                @php $afterArrivalPercent = (int) config('bookings.after_arrival_cancellation_percent'); @endphp
                <form action="{{ route('admin.bookings.cancel-stay', $booking) }}" method="POST" class="mb-3"
                      onsubmit="return confirm('The guest has arrived and is cancelling the stay. A {{ $afterArrivalPercent }}% cancellation charge ({{ npr($booking->total_price * $afterArrivalPercent / 100) }}) applies, plus any extra charges. Continue?');">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-outline-danger w-100">Cancel Stay ({{ $afterArrivalPercent }}% charge)</button>
                    <small class="text-muted d-block mt-1">For a guest who has arrived and cancels the rest of the stay.</small>
                </form>
            @elseif($booking->status === \App\Models\Booking::STATUS_CHECKED_OUT)
                <form action="{{ route('admin.bookings.complete', $booking) }}" method="POST" class="mb-3">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-admin-primary w-100">Mark Completed</button>
                </form>
            @endif

            @if(in_array($booking->status, ['pending', 'confirmed', 'cancelled'], true))
                @error('status')
                    <div class="alert alert-danger py-2 small">{{ $message }}</div>
                @enderror
                <details class="mb-2">
                    <summary class="text-muted small">Manual status override</summary>
                    <form action="{{ route('admin.bookings.status', $booking) }}" method="POST" class="mt-2">
                        @csrf @method('PATCH')
                        <select name="status" class="form-select mb-3">
                            @foreach(['pending', 'confirmed', 'cancelled'] as $status)
                                <option value="{{ $status }}" {{ $booking->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline-dark w-100">Update Status</button>
                    </form>
                </details>
            @else
                <p class="text-muted small mb-0">This booking has moved past manual status changes ({{ ucfirst(str_replace('_', ' ', $booking->status)) }}). Use the actions above instead.</p>
            @endif
        </div>

        <div class="table-section">
            <h2>Payment</h2>
            @foreach($booking->payments->filter->needsReview() as $flagged)
                <div class="alert alert-danger">
                    <strong>Needs review</strong> ({{ payment_provider_label($flagged->provider) }} {{ npr($flagged->amount) }}, ref {{ $flagged->transaction_uuid }},
                    flagged {{ $flagged->review_flagged_at->format('d M Y, g:i A') }}):
                    {{ $flagged->review_reason }}
                    <form method="POST" action="{{ route('admin.payments.resolve-review', $flagged) }}" class="mt-2">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="note" class="form-control form-control-sm mb-2" maxlength="500" placeholder="What was done (refund ref, re-confirmed, rebooked…)" required>
                        <button type="submit" class="btn btn-sm btn-outline-dark">Mark resolved</button>
                    </form>
                </div>
            @endforeach
            @php $latestPayment = $booking->payments->sortByDesc('created_at')->first(); @endphp
            @if($latestPayment)
                @if($latestPayment->status === 'completed' && $booking->status !== 'confirmed')
                    <div class="alert alert-danger">
                        Payment was completed but this booking could not be auto-confirmed
                        (the room was no longer available for these dates). Please review —
                        a refund or manual reassignment may be required.
                    </div>
                @endif
                @if($latestPayment->provider === 'cash' && $latestPayment->status === 'pending' && $booking->status === \App\Models\Booking::STATUS_PENDING)
                    <div class="alert alert-info small">
                        The guest chose to <strong>pay in cash at the hotel</strong>. Contact them to confirm, then set the
                        booking to Confirmed (Manual status override) — otherwise it is released
                        {{ config('payments.methods.cash.hold_hours') }} hours after they chose Cash.
                        Record the cash below when it is received.
                    </div>
                @endif
                @if($latestPayment->provider === 'bank_transfer' && $latestPayment->status === 'pending' && blank(app(\App\Services\SettingsService::class)->current()->bank_account_number))
                    <div class="alert alert-warning small">
                        The guest chose <strong>Bank Transfer</strong>, but no account number is set in Settings yet, so
                        they were told the hotel will send the account details. Please contact them
                        ({{ $booking->phone }}, {{ $booking->email }}) with the bank details.
                    </div>
                @endif
                <table class="table">
                    <tr><td class="text-muted">Provider</td><td>{{ payment_provider_label($latestPayment->provider) }}</td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="status {{ $latestPayment->status === 'completed' ? 'confirmed' : $latestPayment->status }}">{{ ucfirst($latestPayment->status) }}</span></td></tr>
                    <tr><td class="text-muted">Amount</td><td>{{ npr($latestPayment->amount) }}</td></tr>
                    <tr><td class="text-muted">Transaction Ref</td><td>{{ $latestPayment->reference_id ?: '—' }}</td></tr>
                    @if($latestPayment->gatewayTransactionId())
                    <tr><td class="text-muted">{{ payment_provider_label($latestPayment->provider) }} Transaction ID</td><td>{{ $latestPayment->gatewayTransactionId() }}</td></tr>
                    @endif
                    <tr><td class="text-muted">Paid At</td><td>{{ $latestPayment->paid_at?->format('d M Y, g:i A') ?: '—' }}</td></tr>
                </table>
                @if($booking->payments->count() > 1)
                    <p class="text-muted small mb-0">{{ $booking->payments->count() }} payment attempts on record for this booking.</p>
                @endif
            @else
                <p class="text-muted mb-0">No payment attempt has been made yet.</p>
            @endif

            @php
                $balance = max(0, $booking->outstandingBalance());
                $pendingBank = $booking->payments->where('provider', 'bank_transfer')->where('status', 'pending')->sortByDesc('id')->first();
                $bankAmount = $pendingBank ? min((float) $pendingBank->amount ?: $balance, $balance) : $balance;
                $deductionPercent = $booking->appliedCancellationPercent(app(\App\Services\BookingService::class)->cancellationDeductionPercent());
            @endphp
            @if($booking->wasCancelledAfterArrival())
                <div class="alert alert-warning small mt-3">
                    Stay cancelled after arrival: {{ $deductionPercent }}% cancellation charge ({{ npr($booking->cancellationCharge($deductionPercent)) }})@if($booking->chargesTotal() > 0) + extra charges {{ npr($booking->chargesTotal()) }}@endif.
                    @if($booking->outstandingBalance() > 0)
                        <strong>Guest owes {{ npr($booking->outstandingBalance()) }}</strong> — record it below.
                    @endif
                </div>
            @endif
            @if($booking->status === \App\Models\Booking::STATUS_CANCELLED && $booking->amountPaid() > 0)
                <div class="alert alert-warning small mt-3">
                    Cancellation policy: {{ $deductionPercent }}% of the booking ({{ npr($booking->cancellationCharge($deductionPercent)) }}) is kept.
                    <strong>Refund due to guest: {{ npr($booking->refundDue($deductionPercent)) }}</strong> — refund manually, then record it below.
                </div>
            @endif
            @include('admin.bookings._refunds')
            @if($balance > 0 && ($booking->status !== \App\Models\Booking::STATUS_CANCELLED || $booking->wasCancelledAfterArrival()))
                <hr>
                <h3 class="h6">Record Payment Received at the Hotel</h3>
                <p class="text-muted small">
                    Outstanding balance: <strong>{{ npr($balance) }}</strong>. Enter the amount actually received.
                    If the guest pays part by QR and part in cash, record each part separately.
                </p>
                <form action="{{ route('admin.bookings.record-payment', $booking) }}" method="POST" onsubmit="return confirm('Record ' + this.amount.value + ' NPR as received (' + this.provider.options[this.provider.selectedIndex].text + ') for this booking?');">
                    @csrf @method('PATCH')
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="record-provider">Received by</label>
                            <select id="record-provider" name="provider" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="hotel_qr">QR at Hotel (eSewa/Fonepay)</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="record-amount">Amount (NPR)</label>
                            <input id="record-amount" type="number" name="amount" class="form-control" step="0.01" min="0.01" max="{{ number_format($balance, 2, '.', '') }}" value="{{ number_format($balance, 2, '.', '') }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference / Note (optional)</label>
                        <input type="text" name="reference" class="form-control" maxlength="255" placeholder="e.g. QR transaction ID, receipt #, staff initials">
                    </div>
                    <button type="submit" class="btn-admin-primary w-100">Record Payment</button>
                </form>

                @if(config('payments.methods.bank_transfer.enabled') || $booking->payments->where('provider', 'bank_transfer')->where('status', 'pending')->isNotEmpty())
                    <hr>
                    <h3 class="h6">Record Bank Transfer Received</h3>
                    <p class="text-muted small">Only after the {{ npr($bankAmount) }} has actually arrived in the hotel's account. The guest is emailed that the booking is confirmed.</p>
                    <form action="{{ route('admin.bookings.record-payment', $booking) }}" method="POST" onsubmit="return confirm('Confirm that {{ npr($bankAmount) }} was received by bank transfer for this booking?');">
                        @csrf @method('PATCH')
                        <input type="hidden" name="provider" value="bank_transfer">
                        <div class="mb-3">
                            <label class="form-label">Bank Transaction Reference (optional)</label>
                            <input type="text" name="reference" class="form-control" maxlength="255" placeholder="e.g. bank reference / voucher no.">
                        </div>
                        <button type="submit" class="btn-admin-primary w-100">Confirm Bank Transfer Received ({{ npr($bankAmount) }})</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
