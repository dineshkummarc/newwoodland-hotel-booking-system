<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') | New Woodland Hotel</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo/logo.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('styles')
</head>

<body class="admin-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <a href="{{ route('admin.dashboard') }}" class="brand-text">Woodland <span>Admin</span></a>
            <ul class="sidebar-nav">
                <li><a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
                <li><a href="{{ route('admin.rooms.index') }}" class="{{ request()->routeIs('admin.rooms.*') ? 'active' : '' }}"><i class="bi bi-door-open-fill"></i> Rooms</a></li>
                <li><a href="{{ route('admin.bookings.index') }}" class="{{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}"><i class="bi bi-calendar-check-fill"></i> Bookings</a></li>
                <li><a href="{{ route('admin.gallery.index') }}" class="{{ request()->routeIs('admin.gallery.*') ? 'active' : '' }}"><i class="bi bi-images"></i> Gallery</a></li>
                <li><a href="{{ route('admin.facilities.index') }}" class="{{ request()->routeIs('admin.facilities.*') ? 'active' : '' }}"><i class="bi bi-stars"></i> Facilities</a></li>
                <li><a href="{{ route('admin.testimonials.index') }}" class="{{ request()->routeIs('admin.testimonials.*') ? 'active' : '' }}"><i class="bi bi-chat-quote-fill"></i> Testimonials</a></li>
                <li><a href="{{ route('admin.faqs.index') }}" class="{{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}"><i class="bi bi-question-circle-fill"></i> FAQs</a></li>
                <li>
                    <a href="{{ route('admin.messages.index') }}" class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">
                        <i class="bi bi-envelope-fill"></i> Messages
                        @if($unreadMessagesCount > 0)<span class="badge-count">{{ $unreadMessagesCount }}</span>@endif
                    </a>
                </li>
                <li><a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"><i class="bi bi-gear-fill"></i> Settings</a></li>
                <li><a href="{{ route('admin.profile.edit') }}" class="{{ request()->routeIs('admin.profile.*') ? 'active' : '' }}"><i class="bi bi-person-fill"></i> Profile</a></li>
                <li><a href="{{ route('home') }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View Site</a></li>
                <li>
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 text-start w-100" style="all:unset; cursor:pointer; display:flex; align-items:center; gap:.65rem; padding:.7rem .9rem; color:rgba(255,255,255,.8);">
                            <i class="bi bi-box-arrow-left"></i> Logout
                        </button>
                    </form>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="admin-topbar">
                <h1>@yield('title', 'Dashboard')</h1>
                <a href="{{ route('admin.messages.index') }}" class="notification-bell">
                    <i class="bi bi-bell-fill"></i>
                    @if($unreadMessagesCount > 0)<span class="badge-count">{{ $unreadMessagesCount }}</span>@endif
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/vendor/chart.min.js') }}"></script>
    <script src="{{ asset('js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
