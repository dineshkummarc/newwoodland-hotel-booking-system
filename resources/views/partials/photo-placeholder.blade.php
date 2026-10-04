{{--
    Branded stand-in for a hotel photograph the client has not provided yet.
    It names the photo that is needed and is never presented as a real
    Newwoodland photo (plain text, no role="img"/alt).
    Params: $label (subject, e.g. "Deluxe Room"), $icon (bootstrap-icon),
            $plural (bool: "Client Photos Required"), $class (extra classes).
--}}
<div class="photo-placeholder {{ $class ?? '' }}">
    <i class="bi {{ $icon ?? 'bi-camera' }}" aria-hidden="true"></i>
    <span class="photo-placeholder__label">{{ $label }}</span>
    <small class="photo-placeholder__note">Client {{ ($plural ?? false) ? 'Photos' : 'Photo' }} Required</small>
</div>
