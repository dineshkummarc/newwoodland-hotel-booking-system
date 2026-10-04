<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An identity document recorded by staff for a stay. Staff-only: never
 * exposed on any customer-facing page, the number is encrypted at rest and
 * hidden from serialization, and the file sits on the private disk.
 */
class GuestDocument extends Model
{
    /**
     * Hotel policy (client-confirmed): every guest presents one of these
     * photo IDs at check-in. Only these can be recorded.
     */
    public const TYPES = [
        'citizenship' => 'Citizenship Certificate',
        'student_id' => 'Student ID Card',
        'office_id' => 'Office / Company ID Card',
    ];

    /**
     * Types accepted before the policy was confirmed. No longer selectable,
     * kept only so records made with them still display their label.
     */
    public const LEGACY_TYPES = [
        'national_id' => 'National ID Card',
        'passport' => 'Passport',
        'driving_license' => 'Driving Licence',
        'other' => 'Other official photo ID',
    ];

    public const DISK = 'local';

    protected $fillable = [
        'booking_id', 'document_type', 'document_number', 'issuing_country',
        'file_path', 'file_mime', 'file_size', 'recorded_by',
    ];

    protected $hidden = ['document_number', 'file_path'];

    protected function casts(): array
    {
        return [
            'document_number' => 'encrypted',
            'file_size' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'recorded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? self::LEGACY_TYPES[$this->document_type] ?? ucfirst($this->document_type);
    }

    /** e.g. "••••5678" — enough for staff to match a document, not to copy it. */
    public function maskedNumber(): string
    {
        $number = (string) $this->document_number;

        return strlen($number) <= 4 ? str_repeat('•', strlen($number)) : str_repeat('•', 4).substr($number, -4);
    }
}
