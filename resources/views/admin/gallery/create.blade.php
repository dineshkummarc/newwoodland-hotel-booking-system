@extends('layouts.admin')

@section('title', 'Add Gallery Image')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.gallery.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div>
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}">
            </div>
            <div>
                <label class="form-label">Category *</label>
                <select name="category" class="form-select" required>
                    @foreach(['rooms', 'pool', 'restaurant', 'garden'] as $cat)
                        <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full-width">
                <label class="form-label">Image *</label>
                <div class="upload-box" data-dropzone>
                    <i class="bi bi-cloud-arrow-up fs-1"></i>
                    <p class="mb-0">Click or drag an image here (JPG/PNG/WebP, max 4MB)</p>
                    <input type="file" name="image" accept="image/*" data-image-input="preview" class="form-control mt-3" required>
                    <img id="preview" alt="Selected image preview" class="preview" style="display:none;">
                </div>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Add to Gallery</button>
            <a href="{{ route('admin.gallery.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
