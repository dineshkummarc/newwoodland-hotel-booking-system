{{-- Incidental charges (folio) for a stay. Shown once a stay has started or
     whenever charges exist; adding is limited to checked-in/checked-out stays. --}}
@php
    $charges = $booking->charges->sortBy('charged_at');
    $canVoid = auth('admin')->user()?->hasFullAccess();
@endphp
@if($booking->acceptsCharges() || $charges->isNotEmpty())
<div class="table-section mt-4">
    <h2>Extra Charges</h2>
    <table class="table align-middle">
        <thead><tr><th>Date</th><th>Item</th><th class="text-end">Qty × Price</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
            @forelse($charges as $charge)
            <tr class="{{ $charge->isVoided() ? 'text-muted' : '' }}">
                <td class="small text-nowrap">{{ $charge->charged_at->format('d M, g:i A') }}</td>
                <td>
                    <span class="{{ $charge->isVoided() ? 'text-decoration-line-through' : '' }}">{{ $charge->description }}</span>
                    <div class="small text-muted">{{ $charge->categoryLabel() }}@if($charge->addedBy) · added by {{ $charge->addedBy->name }}@endif</div>
                    @if($charge->notes)<div class="small text-muted">{{ $charge->notes }}</div>@endif
                    @if($charge->isVoided())
                        <div class="small text-danger">Voided{{ $charge->voidedBy ? ' by '.$charge->voidedBy->name : '' }}: {{ $charge->void_reason }}</div>
                    @endif
                    @if($canVoid && ! $charge->isVoided() && $booking->acceptsCharges())
                        <details class="mt-1">
                            <summary class="small">Correct this charge</summary>
                            <form action="{{ route('admin.bookings.charges.update', [$booking, $charge]) }}" method="POST" class="row g-1 mt-1">
                                @csrf @method('PUT')
                                <div class="col-12">
                                    <select name="category" class="form-select form-select-sm" required>
                                        @foreach(\App\Models\BookingCharge::CATEGORIES as $key => $label)
                                            <option value="{{ $key }}" @selected($charge->category === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12"><input type="text" name="description" class="form-control form-control-sm" maxlength="255" value="{{ $charge->description }}" required></div>
                                <div class="col-6"><input type="number" name="unit_price" class="form-control form-control-sm" step="0.01" min="0.01" value="{{ $charge->unit_price }}" required></div>
                                <div class="col-6"><input type="number" name="quantity" class="form-control form-control-sm" min="1" max="999" value="{{ $charge->quantity }}" required></div>
                                <div class="col-12"><input type="text" name="notes" class="form-control form-control-sm" maxlength="1000" value="{{ $charge->notes }}" placeholder="Notes (optional)"></div>
                                <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-dark">Save correction</button></div>
                            </form>
                        </details>
                    @endif
                </td>
                <td class="text-end small">{{ $charge->quantity }} × {{ npr($charge->unit_price) }}</td>
                <td class="text-end">{{ npr($charge->amount) }}</td>
                <td class="text-end">
                    @if($canVoid && ! $charge->isVoided() && $booking->acceptsCharges())
                        <form action="{{ route('admin.bookings.charges.void', [$booking, $charge]) }}" method="POST" class="d-flex gap-1"
                              onsubmit="return confirm('Void this charge? It stays on record but is no longer owed.');">
                            @csrf @method('PATCH')
                            <input type="text" name="void_reason" class="form-control form-control-sm" placeholder="Reason" maxlength="255" required>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Void</button>
                        </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-muted">No extra charges yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($booking->acceptsCharges())
        <h3 class="h6 mt-3">Add a Charge</h3>
        <form action="{{ route('admin.bookings.charges.store', $booking) }}" method="POST" class="row g-2">
            @csrf
            <div class="col-md-4">
                <select name="category" class="form-select" required>
                    @foreach(\App\Models\BookingCharge::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <input type="text" name="description" class="form-control" maxlength="255" placeholder="Description (e.g. Dinner for 2)" value="{{ old('description') }}" required>
            </div>
            <div class="col-md-4">
                <input type="number" name="unit_price" class="form-control" step="0.01" min="0.01" placeholder="Unit price (NPR)" value="{{ old('unit_price') }}" required>
            </div>
            <div class="col-md-3">
                <input type="number" name="quantity" class="form-control" min="1" max="999" value="{{ old('quantity', 1) }}" required>
            </div>
            <div class="col-md-5">
                <input type="text" name="notes" class="form-control" maxlength="1000" placeholder="Notes (optional)" value="{{ old('notes') }}">
            </div>
            <div class="col-12">
                <button type="submit" class="btn-admin-primary">Add Charge</button>
                <span class="small text-muted ms-2">Collect it with “Record Payment Received at the Hotel” — the balance due includes charges.</span>
            </div>
        </form>
    @endif
</div>
@endif
