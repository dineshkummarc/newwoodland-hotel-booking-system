@extends('layouts.admin')

@section('title', 'Add Testimonial')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.testimonials.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('admin.testimonials._form', ['testimonial' => new \App\Models\Testimonial])
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Create Testimonial</button>
            <a href="{{ route('admin.testimonials.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
