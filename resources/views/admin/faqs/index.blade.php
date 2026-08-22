@extends('layouts.admin')

@section('title', 'FAQs')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.faqs.create') }}" class="btn-admin-primary"><i class="bi bi-plus-lg"></i> Add FAQ</a>
</div>

<div class="table-section">
    <table class="table align-middle">
        <thead><tr><th>Question</th><th>Order</th><th></th></tr></thead>
        <tbody>
            @forelse($faqs as $faq)
            <tr>
                <td><strong>{{ $faq->question }}</strong><br><span class="text-muted small">{{ Str::limit($faq->answer, 80) }}</span></td>
                <td>
                    <form action="{{ route('admin.faqs.move', [$faq, 'up']) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-outline-dark"><i class="bi bi-arrow-up"></i></button></form>
                    <form action="{{ route('admin.faqs.move', [$faq, 'down']) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-outline-dark"><i class="bi bi-arrow-down"></i></button></form>
                </td>
                <td class="text-end">
                    <a href="{{ route('admin.faqs.edit', $faq) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                    <form action="{{ route('admin.faqs.destroy', $faq) }}" method="POST" class="d-inline" data-confirm-delete="Delete this FAQ?">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="3" class="text-muted">No FAQs yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
