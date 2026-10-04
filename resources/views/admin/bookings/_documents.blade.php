{{-- Staff-only ID/passport records (config bookings.guest_documents). Numbers
     are shown masked; files are streamed only through the staff route. --}}
@if(config('bookings.guest_documents.enabled'))
@php $documents = $booking->guestDocuments; @endphp
<div class="table-section mt-4">
    <h2>Identity Documents</h2>
    <p class="text-muted small">Staff only — never shown to the guest. Record only what the hotel is required to keep.</p>
    @if($documents->isNotEmpty())
    <table class="table align-middle">
        <thead><tr><th>Type</th><th>Number</th><th>Country</th><th>Recorded</th><th></th></tr></thead>
        <tbody>
            @foreach($documents as $document)
            <tr>
                <td>{{ $document->typeLabel() }}</td>
                <td><code>{{ $document->maskedNumber() }}</code></td>
                <td>{{ $document->issuing_country ?: '—' }}</td>
                <td class="small">{{ $document->created_at->format('d M Y') }}@if($document->recordedBy) · {{ $document->recordedBy->name }}@endif</td>
                <td class="text-end text-nowrap">
                    @if($document->file_path)
                        <a href="{{ route('admin.bookings.documents.download', [$booking, $document]) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-download"></i> File</a>
                    @endif
                    @if(auth('admin')->user()?->hasFullAccess())
                        <form action="{{ route('admin.bookings.documents.destroy', [$booking, $document]) }}" method="POST" class="d-inline" data-confirm-delete="Delete this identity document record{{ $document->file_path ? ' and its file' : '' }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <form action="{{ route('admin.bookings.documents.store', $booking) }}" method="POST" enctype="multipart/form-data" class="row g-2">
        @csrf
        <div class="col-md-4">
            <select name="document_type" class="form-select" required>
                @foreach(\App\Models\GuestDocument::TYPES as $key => $label)
                    <option value="{{ $key }}" @selected(old('document_type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="document_number" class="form-control" maxlength="50" placeholder="Document number" autocomplete="off" required>
        </div>
        <div class="col-md-4">
            <input type="text" name="issuing_country" class="form-control" maxlength="100" placeholder="Issuing country" value="{{ old('issuing_country', $booking->country) }}">
        </div>
        @if(config('bookings.guest_documents.allow_upload'))
        <div class="col-md-8">
            <input type="file" name="document_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
            <small class="text-muted">Optional scan — JPG, PNG or PDF, up to {{ intdiv(config('bookings.guest_documents.max_upload_kb'), 1024) }} MB. Stored privately.</small>
        </div>
        @endif
        <div class="col-12">
            <button type="submit" class="btn-admin-primary">Record Document</button>
        </div>
    </form>
</div>
@endif
