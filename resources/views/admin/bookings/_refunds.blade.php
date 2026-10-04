{{-- Manual refund records. Money is returned outside the app; this is the
     record of what was owed and returned. Recording is admin-only. --}}
@php
    $refundList = $booking->refunds->sortByDesc('id');
    $refundable = $booking->refundableAmount();
    $canRefund = auth('admin')->user()?->hasFullAccess();
    $suggested = $booking->status === \App\Models\Booking::STATUS_CANCELLED
        ? $booking->refundDue($deductionPercent)
        : max(0, -$booking->outstandingBalance());
    $completedPayments = $booking->payments->where('status', 'completed');
@endphp
@if($refundList->isNotEmpty() || ($canRefund && $refundable > 0))
    <hr>
    <h3 class="h6">Refunds</h3>
    @foreach($refundList as $refund)
        <div class="border rounded p-2 mb-2 small">
            <div class="d-flex justify-content-between">
                <strong>{{ npr($refund->amount) }}</strong>
                <span class="status {{ $refund->status === 'completed' ? 'confirmed' : $refund->status }}">{{ $refund->statusLabel() }}</span>
            </div>
            <div class="text-muted">
                {{ $refund->methodLabel() }}@if($refund->reference) · Ref: {{ $refund->reference }}@endif
                · recorded {{ $refund->created_at->format('d M Y') }}@if($refund->recordedBy) by {{ $refund->recordedBy->name }}@endif
                @if($refund->refunded_at)<br>Paid out {{ $refund->refunded_at->format('d M Y, g:i A') }}@if($refund->processedBy) by {{ $refund->processedBy->name }}@endif @endif
            </div>
            @if($refund->notes)<div class="text-muted">{{ $refund->notes }}</div>@endif
            @if($canRefund && $refund->isPending())
                <div class="d-flex gap-1 mt-2">
                    <form action="{{ route('admin.bookings.refunds.complete', [$booking, $refund]) }}" method="POST" class="d-flex gap-1 flex-grow-1"
                          onsubmit="return confirm('Confirm {{ npr($refund->amount) }} has been returned to the guest?');">
                        @csrf @method('PATCH')
                        <input type="text" name="reference" class="form-control form-control-sm" maxlength="255" placeholder="Reference (optional)">
                        <button type="submit" class="btn btn-sm btn-outline-success text-nowrap">Mark paid</button>
                    </form>
                    <form action="{{ route('admin.bookings.refunds.cancel', [$booking, $refund]) }}" method="POST"
                          onsubmit="return confirm('Cancel this pending refund?');">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel</button>
                    </form>
                </div>
            @endif
        </div>
    @endforeach

    @if($canRefund && $refundable > 0)
        @if($errors->refund->any())
            <div class="alert alert-danger small">
                @foreach($errors->refund->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        <form action="{{ route('admin.bookings.refunds.store', $booking) }}" method="POST" class="mt-2"
              onsubmit="return confirm('Record this refund?');">
            @csrf
            <p class="text-muted small mb-2">Up to {{ npr($refundable) }} can still be refunded. This only records the refund — return the money through the original channel first.</p>
            <div class="row g-2">
                <div class="col-6">
                    <input type="number" name="amount" class="form-control form-control-sm" step="0.01" min="0.01" max="{{ $refundable }}"
                           value="{{ old('amount', $suggested > 0 ? min($suggested, $refundable) : '') }}" placeholder="Amount" required>
                </div>
                <div class="col-6">
                    <select name="method" class="form-select form-select-sm" required>
                        @foreach(\App\Models\Refund::METHODS as $key => $label)
                            <option value="{{ $key }}" @selected(old('method', $completedPayments->sortByDesc('id')->first()?->provider) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($completedPayments->count() > 1)
                <div class="col-12">
                    <select name="payment_id" class="form-select form-select-sm">
                        <option value="">Refunding which payment? (optional)</option>
                        @foreach($completedPayments as $p)
                            <option value="{{ $p->id }}" @selected((string) old('payment_id') === (string) $p->id)>{{ payment_provider_label($p->provider) }} — {{ npr($p->amount) }} ({{ $p->paid_at?->format('d M Y') }})</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-12">
                    <input type="text" name="reference" class="form-control form-control-sm" maxlength="255" placeholder="Reference (bank/wallet txn, receipt #)" value="{{ old('reference') }}">
                </div>
                <div class="col-12">
                    <input type="text" name="notes" class="form-control form-control-sm" maxlength="1000" placeholder="Notes (optional)" value="{{ old('notes') }}">
                </div>
                <div class="col-12 form-check ms-1">
                    <input type="checkbox" name="completed" value="1" class="form-check-input" id="refund-completed" @checked(old('completed'))>
                    <label for="refund-completed" class="form-check-label small">Money has already been returned to the guest</label>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-outline-dark w-100">Record Refund</button>
                </div>
            </div>
        </form>
    @endif
@endif
