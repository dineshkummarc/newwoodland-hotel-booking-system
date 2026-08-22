@extends('layouts.admin')

@section('title', 'Edit FAQ')

@section('content')
<div class="admin-panel">
    <form action="{{ route('admin.faqs.update', $faq) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-grid">
            <div class="full-width">
                <label class="form-label">Question *</label>
                <input type="text" name="question" class="form-control" value="{{ old('question', $faq->question) }}" required>
            </div>
            <div class="full-width">
                <label class="form-label">Answer *</label>
                <textarea name="answer" class="form-control" rows="4" required>{{ old('answer', $faq->answer) }}</textarea>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn-admin-primary">Save Changes</button>
            <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
