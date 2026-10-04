<!-- FOOTER -->
<footer id="footer">
    <div class="container">
        <div class="row gy-5">
            <div class="col-lg-4">
                <a href="{{ route('home') }}" class="footer-logo d-flex align-items-center gap-2 mb-3 text-white text-decoration-none">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 280" style="height: 40px; width: auto;" aria-hidden="true">
                        <path d="M 250,30 C 240,55 250,75 250,75 C 250,75 260,55 250,30 Z" fill="#20bf6b" />
                        <path d="M 225,45 C 220,65 235,80 235,80 C 235,80 245,60 225,45 Z" fill="#26de81" />
                        <path d="M 275,45 C 280,65 265,80 265,80 C 265,80 255,60 275,45 Z" fill="#10ac84" />
                        <circle cx="160" cy="120" r="10" fill="#26de81" />
                        <circle cx="250" cy="50" r="10" fill="#26de81" />
                        <circle cx="340" cy="120" r="10" fill="#26de81" />
                        <path d="M 240,190 Q 250,150 240,125 Q 220,135 180,145 Q 210,165 240,190 Z" fill="#d4af37" />
                        <path d="M 260,190 Q 250,150 260,125 Q 280,135 320,145 Q 290,165 260,190 Z" fill="#d4af37" />
                    </svg>
                    <span class="fs-4 font-heading fw-bold">Newwoodland <span class="text-gold">Hotel &amp; Lounge</span></span>
                </a>
                <p class="footer-text">
                    Experience luxury, comfort, and authentic Nepalese hospitality at Newwoodland Hotel & Lounge in Bhadrapur-8, Jhapa, Nepal.
                </p>
                <div class="footer-social">
                    @if($facebookUrl ?? null)<a href="{{ $facebookUrl }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="bi bi-facebook"></i></a>@endif
                    @if($instagramUrl ?? null)<a href="{{ $instagramUrl }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a>@endif
                    @if($youtubeUrl ?? null)<a href="{{ $youtubeUrl }}" target="_blank" rel="noopener" aria-label="Youtube"><i class="bi bi-youtube"></i></a>@endif
                    @if($tiktokUrl ?? null)<a href="{{ $tiktokUrl }}" target="_blank" rel="noopener" aria-label="TikTok"><i class="bi bi-tiktok"></i></a>@endif
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('about') }}">About Us</a></li>
                    <li><a href="{{ route('rooms.index') }}">Rooms</a></li>
                    <li><a href="{{ route('facilities') }}">Facilities</a></li>
                    <li><a href="{{ route('gallery') }}">Gallery</a></li>
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6">
                <h4>Our Services</h4>
                <ul class="footer-links">
                    <li><a href="{{ route('booking') }}">Online Booking</a></li>
                    <li><a href="{{ route('facilities') }}">Restaurant & Bar</a></li>
                    <li><a href="{{ route('facilities') }}">Swimming Pool</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h4>Contact Information</h4>
                <ul class="footer-contact">
                    <li><i class="bi bi-geo-alt-fill text-gold me-2"></i> {{ $hotelAddress ?? 'Bhadrapur-8, Jhapa, Nepal' }}</li>
                    <li>
                        <i class="bi bi-telephone-fill text-gold me-2"></i>
                        <a href="tel:{{ $hotelPhoneRaw ?? '+9779852690590' }}" class="text-white">{{ $hotelPhone ?? '+977 9852690590' }}</a>
                        @if($hotelPhoneSecondary ?? null)
                            &bull; <a href="tel:{{ $hotelPhoneSecondaryRaw }}" class="text-white">{{ $hotelPhoneSecondary }}</a>
                        @endif
                    </li>
                    <li><i class="bi bi-envelope-fill text-gold me-2"></i> <a href="mailto:{{ $hotelEmail ?? 'woodlandhotel146@gmail.com' }}" class="text-white">{{ $hotelEmail ?? 'woodlandhotel146@gmail.com' }}</a></li>
                    @if($checkinTime ?? null)
                    <li><i class="bi bi-clock-fill text-gold me-2"></i> Check-in {{ \Illuminate\Support\Carbon::parse($checkinTime)->format('g:i A') }} &bull; Check-out {{ \Illuminate\Support\Carbon::parse($checkoutTime)->format('g:i A') }}</li>
                    @endif
                </ul>
            </div>
        </div>

        <hr class="border-secondary mt-5">

        <div class="footer-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <p>© {{ date('Y') }} <strong>Newwoodland Hotel & Lounge</strong>. All Rights Reserved.</p>
            <div class="footer-bottom-links">
                <a href="{{ route('privacy') }}" class="text-white-50">Privacy Policy</a>
                <span class="mx-2 text-white-50">|</span>
                <a href="{{ route('terms') }}" class="text-white-50">Terms &amp; Conditions</a>
                <span class="mx-2 text-white-50">|</span>
                <a href="{{ route('admin.login') }}" class="text-warning"><i class="bi bi-lock-fill me-1"></i>Admin Portal</a>
            </div>
        </div>
    </div>
</footer>

<!-- SCROLL TOP -->
<a href="#" class="scroll-top" id="scrollTopBtn" aria-label="Scroll to top">
    <i class="bi bi-arrow-up-short fs-2"></i>
</a>
