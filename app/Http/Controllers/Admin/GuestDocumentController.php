<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GuestDocument;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Staff-only ID/passport records for a stay (config bookings.guest_documents,
 * off by default). Document numbers are never written to the audit log;
 * files are only ever streamed through download() to signed-in staff.
 */
class GuestDocumentController extends Controller
{
    public function store(Request $request, Booking $booking, AuditLogService $auditLog)
    {
        abort_unless(config('bookings.guest_documents.enabled'), 404);
        $allowUpload = (bool) config('bookings.guest_documents.allow_upload');

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_keys(GuestDocument::TYPES))],
            'document_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
            'issuing_country' => ['nullable', 'string', 'max:100'],
            'document_file' => $allowUpload
                ? ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:'.(int) config('bookings.guest_documents.max_upload_kb')]
                : ['prohibited'],
        ], [
            'document_number.regex' => 'The document number may only contain letters, numbers, spaces, "-" and "/".',
            'document_file.prohibited' => 'Document uploads are not enabled.',
        ]);

        $file = $request->file('document_file');
        $path = $file?->store('guest-documents/'.$booking->id, GuestDocument::DISK);

        $document = $booking->guestDocuments()->create([
            'document_type' => $validated['document_type'],
            'document_number' => trim($validated['document_number']),
            'issuing_country' => $validated['issuing_country'] ?? null,
            'file_path' => $path ?: null,
            'file_mime' => $file?->getMimeType(),
            'file_size' => $file?->getSize(),
            'recorded_by' => $request->user('admin')->id,
        ]);

        // Deliberately no document number in the audit trail.
        $auditLog->log('booking.document_recorded', 'booking', $booking->id, "Recorded a {$document->typeLabel()} for booking {$booking->confirmation_code}.", [
            'document_id' => $document->id,
            'has_file' => $path !== null,
        ]);

        return back()->with('success', 'Identity document recorded.');
    }

    public function download(Request $request, Booking $booking, GuestDocument $document, AuditLogService $auditLog)
    {
        abort_unless(config('bookings.guest_documents.enabled'), 404);
        abort_unless($document->booking_id === $booking->id && $document->file_path, 404);
        abort_unless(Storage::disk(GuestDocument::DISK)->exists($document->file_path), 404);

        $auditLog->log('booking.document_viewed', 'booking', $booking->id, "Viewed the {$document->typeLabel()} file for booking {$booking->confirmation_code}.", [
            'document_id' => $document->id,
        ]);

        return Storage::disk(GuestDocument::DISK)->download(
            $document->file_path,
            "{$booking->confirmation_code}-{$document->document_type}.".pathinfo($document->file_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']
        );
    }

    /** Admin/super_admin only (route group). */
    public function destroy(Booking $booking, GuestDocument $document, AuditLogService $auditLog)
    {
        abort_unless($document->booking_id === $booking->id, 404);

        if ($document->file_path) {
            Storage::disk(GuestDocument::DISK)->delete($document->file_path);
        }
        $document->delete();

        $auditLog->log('booking.document_deleted', 'booking', $booking->id, "Deleted a {$document->typeLabel()} record for booking {$booking->confirmation_code}.", [
            'document_id' => $document->id,
        ]);

        return back()->with('success', 'Identity document deleted.');
    }
}
