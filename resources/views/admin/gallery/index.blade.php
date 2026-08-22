@extends('layouts.admin')

@section('title', 'Gallery')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.gallery.create') }}" class="btn-admin-primary"><i class="bi bi-plus-lg"></i> Add Image</a>
</div>

<div class="table-section">
    <div class="gallery-grid">
        @forelse($images as $image)
        <div class="gallery-card">
            <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $image->title }}">
            <div class="body">
                <strong>{{ $image->title ?: 'Untitled' }}</strong>
                <div class="text-muted small text-capitalize">{{ $image->category }}</div>
            </div>
            <div class="actions">
                <form action="{{ route('admin.gallery.move', [$image, 'up']) }}" method="POST"><button class="btn btn-sm btn-outline-dark" title="Move up">@csrf<i class="bi bi-arrow-up"></i></button></form>
                <form action="{{ route('admin.gallery.move', [$image, 'down']) }}" method="POST"><button class="btn btn-sm btn-outline-dark" title="Move down">@csrf<i class="bi bi-arrow-down"></i></button></form>
                <a href="{{ route('admin.gallery.edit', $image) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                <form action="{{ route('admin.gallery.destroy', $image) }}" method="POST" data-confirm-delete="Delete this image?">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>
        @empty
        <p class="text-muted">No gallery images yet.</p>
        @endforelse
    </div>
    {{ $images->links() }}
</div>
@endsection
