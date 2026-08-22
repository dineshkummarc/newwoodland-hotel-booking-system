@extends('layouts.admin')

@section('title', 'Edit Room')

@section('content')
<div class="admin-panel mb-4">
    <form id="roomForm" action="{{ route('admin.rooms.update', $room) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.rooms._form')
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Save Changes</button>
            <a href="{{ route('admin.rooms.index') }}" class="btn btn-outline-secondary">Back to Rooms</a>
        </div>
    </form>
</div>

<div class="table-section">
    <h2>Images</h2>
    <div class="gallery-grid mb-4">
        @foreach($room->images as $image)
        <div class="gallery-card">
            <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $room->name }}">
            <div class="body">
                @if($image->is_primary)
                    <span class="status confirmed">Primary</span>
                @else
                    <form action="{{ route('admin.rooms.images.primary', [$room, $image]) }}" method="POST">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-sm btn-outline-dark w-100">Set Primary</button>
                    </form>
                @endif
            </div>
            <div class="actions">
                <form action="{{ route('admin.rooms.images.destroy', [$room, $image]) }}" method="POST" data-confirm-delete="Delete this image?">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Delete</button>
                </form>
            </div>
        </div>
        @endforeach
    </div>

    <form action="{{ route('admin.rooms.images.store', $room) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="upload-box" data-dropzone>
            <i class="bi bi-cloud-arrow-up fs-1"></i>
            <p class="mb-0">Click or drag an image here to upload (JPG/PNG/WebP, max 4MB)</p>
            <input type="file" name="image" accept="image/*" data-image-input="newImagePreview" class="form-control mt-3" required>
            <img id="newImagePreview" class="preview" style="display:none;">
        </div>
        <div class="form-check mt-3">
            <input type="checkbox" name="is_primary" class="form-check-input" id="isPrimaryUpload" value="1">
            <label class="form-check-label" for="isPrimaryUpload">Set as primary image</label>
        </div>
        <button type="submit" class="btn-admin-primary mt-3">Upload Image</button>
    </form>
</div>
@endsection
