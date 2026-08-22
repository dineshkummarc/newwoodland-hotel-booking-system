<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', 'New Woodland Hotel offers luxury accommodation, premium dining, modern facilities, and exceptional hospitality in Bhadrapur-8, Jhapa, Nepal.')">
    <meta name="keywords" content="@yield('meta_keywords', 'New Woodland Hotel, Hotel in Bhadrapur, Woodland Hotel Nepal, Jhapa, Hotel Booking')">
    <meta name="author" content="New Woodland Hotel">
    <meta name="robots" content="@yield('meta_robots', 'index, follow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    <title>@yield('title', 'New Woodland Hotel | Hotel in Bhadrapur-8, Jhapa')</title>

    <!-- OPEN GRAPH -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="New Woodland Hotel">
    <meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title', 'New Woodland Hotel | Hotel in Bhadrapur-8, Jhapa')))">
    <meta property="og:description" content="@yield('og_description', trim($__env->yieldContent('meta_description', 'New Woodland Hotel offers luxury accommodation, premium dining, modern facilities, and exceptional hospitality in Bhadrapur-8, Jhapa, Nepal.')))">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="@yield('og_image', asset('assets/images/og-default.jpg'))">

    <!-- TWITTER CARD -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', trim($__env->yieldContent('title', 'New Woodland Hotel | Hotel in Bhadrapur-8, Jhapa')))">
    <meta name="twitter:description" content="@yield('og_description', trim($__env->yieldContent('meta_description', 'New Woodland Hotel offers luxury accommodation, premium dining, modern facilities, and exceptional hospitality in Bhadrapur-8, Jhapa, Nepal.')))">
    <meta name="twitter:image" content="@yield('og_image', asset('assets/images/og-default.jpg'))">

    @stack('structured_data')

    <!-- FAVICON & FONTS -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo/logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- BOOTSTRAP CSS & ICONS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- CUSTOM CSS -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @stack('styles')
</head>

<body class="@yield('body-class', '')">

    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    <!-- BOOTSTRAP JS & CUSTOM SCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/script.js') }}"></script>
    @stack('scripts')
</body>
</html>
