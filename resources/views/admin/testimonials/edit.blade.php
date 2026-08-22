@extends('layouts.admin')

@section('title', 'Edit Testimonial')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.testimonials.update', $testimonial) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.testimonials._form')
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Save Changes</button>
            <a href="{{ route('admin.testimonials.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
