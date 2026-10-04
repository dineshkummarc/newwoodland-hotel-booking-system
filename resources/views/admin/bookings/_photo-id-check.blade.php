{{-- Hotel policy: an official photo ID is presented at check-in (citizenship,
     office/company ID card with photo, or another official photo ID card). --}}
@if(config('bookings.checkin_requires_photo_id'))
<div class="form-check mb-2 text-start">
    <input class="form-check-input" type="checkbox" name="id_verified" value="1" id="id-verified-{{ $idSuffix }}-{{ $booking->id }}" required>
    <label class="form-check-label small" for="id-verified-{{ $idSuffix }}-{{ $booking->id }}">
        Guest presented an official photo ID (citizenship, office/company ID with photo, or other official photo ID)
    </label>
</div>
@endif
