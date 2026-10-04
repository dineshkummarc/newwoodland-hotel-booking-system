{{--
    An About-page photo slot (config/about.php). Shows the hotel's real photo
    once it exists on the public disk; until then a labelled placeholder
    (partials/photo-placeholder) — never a stock photo presented as Newwoodland.
    Params: $path (public-disk path), $alt (describes the real photo),
            $label (photo subject), $icon (bootstrap-icon),
            $class (extra classes for the frame), $eager (bool).
--}}
@php
    $url = site_photo_url($path ?? null);
@endphp
<div class="about-photo {{ $class ?? '' }} {{ $url ? '' : 'about-photo--placeholder' }}">
    @if($url)
        <img src="{{ $url }}" alt="{{ $alt }}"
             @unless($eager ?? false) loading="lazy" @endunless decoding="async">
    @else
        {{-- CLIENT INPUT REQUIRED: real photo for "{{ $label }}" → storage/app/public/{{ $path }} --}}
        @include('partials.photo-placeholder', ['label' => $label, 'icon' => $icon ?? 'bi-camera'])
    @endif
</div>
