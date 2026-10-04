<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function booking(array $overrides = []): Booking
    {
        Setting::factory()->create(['hotel_name' => 'Newwoodland Hotel and Lounge']);
        $room = Room::factory()->create(['name' => 'Deluxe Room', 'room_number' => '204']);

        $booking = Booking::factory()->for($room)->create(array_merge([
            'customer_name' => 'Asha Rai',
            'nightly_rate' => 2000, 'nights' => 2, 'discount_percent' => 10, 'discount_amount' => 400, 'total_price' => 3600,
            'status' => Booking::STATUS_CONFIRMED,
        ], $overrides));
        Payment::factory()->create(['booking_id' => $booking->id, 'provider' => 'esewa', 'amount' => 1800, 'status' => Payment::STATUS_COMPLETED, 'paid_at' => now()]);

        return $booking;
    }

    protected function assertIsPdf($response, Booking $booking): void
    {
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('INV-'.$booking->confirmation_code.'.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_view_contains_the_required_details(): void
    {
        $booking = $this->booking(['deposit_percent' => 50]);
        BookingCharge::create([
            'booking_id' => $booking->id, 'category' => 'minibar', 'description' => 'Soft drinks',
            'unit_price' => 150, 'quantity' => 2, 'amount' => 300, 'charged_at' => now(),
        ]);
        $booking->load(['room', 'payments', 'charges', 'refunds']);

        $html = view('pdf.invoice', [
            'booking' => $booking,
            'hotel' => Setting::first(),
            'invoiceNumber' => app(InvoiceService::class)->invoiceNumber($booking),
            'issuedAt' => now(),
        ])->render();

        foreach ([
            'Newwoodland Hotel and Lounge', 'INV-'.$booking->confirmation_code, $booking->confirmation_code,
            'Asha Rai', $booking->email, 'Deluxe Room', 'Room 204',
            $booking->check_in->format('d M Y'), $booking->check_out->format('d M Y'), $booking->guests_label,
            'NPR 4,000', // base price (2 nights × 2,000)
            'NPR 400',   // discount
            'Soft drinks', 'NPR 3,900', // grand total incl. charge
            'NPR 1,800', // paid
            'NPR 2,100', // balance
            '50%', 'eSewa', 'confirmed',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
    }

    public function test_account_holder_can_download_their_own_invoice(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking(['user_id' => $user->id]);

        $this->assertIsPdf($this->actingAs($user)->get(route('account.bookings.invoice', $booking->confirmation_code)), $booking);
    }

    public function test_customers_cannot_download_someone_elses_invoice(): void
    {
        $owner = User::factory()->create();
        $booking = $this->booking(['user_id' => $owner->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('account.bookings.invoice', $booking->confirmation_code))
            ->assertNotFound();
    }

    public function test_guest_booking_invoice_cannot_be_fetched_through_an_account(): void
    {
        $booking = $this->booking(['user_id' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('account.bookings.invoice', $booking->confirmation_code))
            ->assertNotFound();
    }

    public function test_invoice_requires_login_or_a_valid_signature(): void
    {
        $booking = $this->booking(['user_id' => null]);

        $this->get(route('account.bookings.invoice', $booking->confirmation_code))->assertRedirect(route('login'));
        $this->get(route('booking.invoice.guest', $booking->confirmation_code))->assertForbidden();

        // A signature for one booking does not work for another.
        $other = Booking::factory()->for(Room::factory())->create();
        $tampered = str_replace($booking->confirmation_code, $other->confirmation_code, $booking->invoiceUrl());
        $this->get($tampered)->assertForbidden();
    }

    public function test_guest_can_download_through_the_signed_email_link_until_it_expires(): void
    {
        $booking = $this->booking(['user_id' => null, 'check_out' => today()->addDays(3)->toDateString()]);

        $this->assertIsPdf($this->get($booking->invoiceUrl()), $booking);

        $expiring = URL::temporarySignedRoute('booking.invoice.guest', now()->addMinute(), ['booking' => $booking->confirmation_code]);
        $this->travel(2)->minutes();
        $this->get($expiring)->assertForbidden();
    }

    public function test_staff_can_download_any_invoice_and_customers_cannot_use_the_admin_route(): void
    {
        $booking = $this->booking();

        $this->assertIsPdf($this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')->get(route('admin.bookings.invoice', $booking)), $booking);

        $this->actingAs(User::factory()->create())->get(route('admin.bookings.invoice', $booking))->assertRedirect(route('admin.login'));
    }

    public function test_confirmation_email_contains_a_signed_invoice_link(): void
    {
        $booking = $this->booking(['user_id' => null]);

        $html = (new \App\Mail\BookingConfirmationMail($booking))->render();

        $this->assertStringContainsString('/booking/'.$booking->confirmation_code.'/invoice?', $html);
        $this->assertStringContainsString('signature=', $html);
    }
}
