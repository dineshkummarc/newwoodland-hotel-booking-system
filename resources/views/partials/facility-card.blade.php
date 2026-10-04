{{-- Facility card (facilities page, home page). Params: $facility. --}}
<article class="facility-card p-4 bg-light rounded-4 shadow-sm h-100">
    <div class="facility-icon mb-3"><i class="bi {{ $facility->icon ?: 'bi-star-fill' }}" aria-hidden="true"></i></div>
    <h3 class="h5 font-heading text-emerald mb-3">{{ $facility->title }}</h3>
    @if($highlights = $facility->highlights())
        <ul class="facility-highlights">
            @foreach($highlights as $highlight)
                <li>{{ $highlight }}</li>
            @endforeach
        </ul>
    @endif
</article>
