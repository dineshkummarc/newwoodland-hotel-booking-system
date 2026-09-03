<!-- NAVBAR -->
<header class="header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 280" style="height: 44px; width: auto;" aria-hidden="true">
                    <path d="M 250,30 C 240,55 250,75 250,75 C 250,75 260,55 250,30 Z" fill="#20bf6b" />
                    <path d="M 225,45 C 220,65 235,80 235,80 C 235,80 245,60 225,45 Z" fill="#26de81" />
                    <path d="M 275,45 C 280,65 265,80 265,80 C 265,80 255,60 275,45 Z" fill="#10ac84" />
                    <path d="M 195,70 C 190,90 210,105 210,105 C 210,105 220,85 195,70 Z" fill="#20bf6b" />
                    <path d="M 305,70 C 310,90 290,105 290,105 C 290,105 280,85 305,70 Z" fill="#26de81" />
                    <path d="M 170,100 C 170,120 190,130 190,130 C 190,130 200,110 170,100 Z" fill="#10ac84" />
                    <path d="M 330,100 C 330,120 310,130 310,130 C 310,130 300,110 330,100 Z" fill="#20bf6b" />
                    <circle cx="160" cy="120" r="10" fill="#26de81" />
                    <circle cx="190" cy="95" r="9" fill="#10ac84" />
                    <circle cx="220" cy="65" r="9.5" fill="#20bf6b" />
                    <circle cx="250" cy="50" r="10" fill="#26de81" />
                    <circle cx="280" cy="65" r="9.5" fill="#10ac84" />
                    <circle cx="310" cy="95" r="9" fill="#20bf6b" />
                    <circle cx="340" cy="120" r="10" fill="#26de81" />
                    <path d="M 240,190 Q 250,150 240,125 Q 220,135 180,145 Q 210,165 240,190 Z" fill="#a52a2a" />
                    <path d="M 260,190 Q 250,150 260,125 Q 280,135 320,145 Q 290,165 260,190 Z" fill="#800000" />
                    <path d="M 245,190 L 255,190 L 258,240 L 242,240 Z" fill="#4a1525" />
                </svg>
                <span class="brand-text">Woodland <span>Hotel</span></span>
            </a>

            <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-1"></i>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('rooms.*') ? 'active' : '' }}" href="{{ route('rooms.index') }}">Rooms</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('facilities') ? 'active' : '' }}" href="{{ route('facilities') }}">Facilities</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('gallery') ? 'active' : '' }}" href="{{ route('gallery') }}">Gallery</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">Contact</a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    @auth
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i> {{ Str::before(auth()->user()->name, ' ') }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('account.bookings') }}"><i class="bi bi-calendar3 me-2"></i>My Bookings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">
                            <i class="bi bi-person me-1"></i> Sign In
                        </a>
                        <a class="nav-link {{ request()->routeIs('register') ? 'active' : '' }}" href="{{ route('register') }}">
                            Register
                        </a>
                    @endauth

                    <a href="{{ route('booking') }}" class="btn-book">
                        <i class="bi bi-calendar-check me-1"></i> Book Now
                    </a>
                </div>
            </div>
        </div>
    </nav>
</header>
