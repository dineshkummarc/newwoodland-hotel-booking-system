@extends('layouts.app')

@section('title', 'Redirecting to eSewa | Newwoodland Hotel & Lounge')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<section class="page-banner text-center" style="min-height: 60vh; display: flex; align-items: center;">
    <div class="container">
        <div class="hero-content">
            <div class="spinner-border text-warning mb-4" role="status">
                <span class="visually-hidden">Redirecting…</span>
            </div>
            <h1 class="font-heading">Redirecting to eSewa…</h1>
            <p class="lead text-white-50 max-w-700 mx-auto mt-3">
                Please wait while we take you to eSewa's secure payment page. If you are not redirected automatically, use the button below.
            </p>

            <form id="esewaForm" action="{{ $actionUrl }}" method="POST">
                @foreach($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="btn-gold py-3 px-5 fs-6 mt-3">Continue to eSewa</button>
            </form>
        </div>
    </div>
</section>

<script>
    document.getElementById('esewaForm').submit();
</script>
@endsection
