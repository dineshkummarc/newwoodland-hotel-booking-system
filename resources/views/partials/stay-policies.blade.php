{{-- Client-confirmed policies a guest should know before arriving. --}}
@php
    $beforeArrivalPercent = app(\App\Services\BookingService::class)->cancellationDeductionPercent();
    $hotelTimes = app(\App\Services\SettingsService::class)->current();
    $fmtTime = fn ($t) => filled($t) ? \Illuminate\Support\Carbon::parse($t)->format('g:i A') : null;
@endphp
<ul class="list-unstyled stay-policies text-muted {{ $class ?? 'mb-0' }}">
    @if($fmtTime($hotelTimes->checkout_time))
    <li class="mb-1"><i class="bi bi-clock me-1"></i>
        @if($fmtTime($hotelTimes->checkin_time)) Check-in from {{ $fmtTime($hotelTimes->checkin_time) }}; @endif
        check-out by {{ $fmtTime($hotelTimes->checkout_time) }} on your check-out date, whatever time you arrived.</li>
    @endif
    <li class="mb-1"><i class="bi bi-person-vcard me-1"></i> Please bring a valid photo ID for check-in: {{ collect(\App\Models\GuestDocument::TYPES)->values()->join(', ', ' or ') }}.</li>
    <li><i class="bi bi-calendar-x me-1"></i> {{ $beforeArrivalPercent ? "Cancellation before arrival: {$beforeArrivalPercent}% of the booking amount is deducted" : 'Free cancellation before arrival' }}; {{ config('bookings.after_arrival_cancellation_percent') }}% charge if the stay is cancelled after check-in.</li>
</ul>
