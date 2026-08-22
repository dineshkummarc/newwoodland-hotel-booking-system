@extends('layouts.admin')

@section('title', 'Edit Gallery Image')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.gallery.update', $image) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div>
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $image->title) }}">
            </div>
            <div>
                <label class="form-label">Category *</label>
                <select name="category" class="form-select" required>
                    @foreach(['rooms', 'pool', 'restaurant', 'garden'] as $cat)
                        <option value="{{ $cat }}" {{ old('category', $image->category) === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="full-width">
                <label class="form-label">Current Image</label><br>
                <img src="{{ asset('storage/'.$image->path) }}" style="max-height:160px;" class="rounded mb-3">
                <label class="form-label">Replace Image (optional)</label>
                <input type="file" name="image" accept="image/*" class="form-control">
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Save Changes</button>
            <a href="{{ route('admin.gallery.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
