<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Booking invoice / receipt as a PDF (dompdf). Everything on it is read
 * from the booking's own server-side records — room price, discount,
 * extra charges, completed payments and completed refunds. Who may download
 * it is decided by the calling route, never here.
 */
class InvoiceService
{
    public function __construct(protected SettingsService $settings) {}

    public function invoiceNumber(Booking $booking): string
    {
        return 'INV-'.$booking->confirmation_code;
    }

    public function download(Booking $booking): Response
    {
        $booking->load([
            'room',
            'payments' => fn ($q) => $q->where('status', 'completed')->orderBy('paid_at'),
            'charges' => fn ($q) => $q->active()->orderBy('charged_at'),
            'refunds' => fn ($q) => $q->where('status', 'completed')->orderBy('refunded_at'),
        ]);

        $pdf = Pdf::loadView('pdf.invoice', [
            'booking' => $booking,
            'hotel' => $this->settings->current(),
            'invoiceNumber' => $this->invoiceNumber($booking),
            'issuedAt' => now(),
        ])->setPaper('a4');

        return $pdf->download($this->invoiceNumber($booking).'.pdf');
    }
}
