<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Services\BookingService;
use App\Services\EsewaPaymentService;
use Database\Seeders\RoomSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * Client-confirmed hotel information: room types/amenities, unpriced rooms,
 * same-type room assignment, the About page figures, and the accepted
 * payment methods (eSewa, Fonepay, Siddhartha Bank transfer, cash on
 * arrival — never Khalti).
 */
class HotelClientInfoTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected function pendingBooking(array $overrides = []): Booking
    {
        $room = Room::factory()->create(['price_per_night' => 1500]);

        return Booking::factory()->for($room)->create(array_merge([
            'nightly_rate' => 1500,
            'total_price' => 3000,
            'nights' => 2,
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }

    protected function bookingPayload(Room $room): array
    {
        return [
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '1 Adult',
            'customer_name' => 'Test Guest',
            'email' => 'guest@example.com',
            'phone' => '+977 9800000000',
        ];
    }

    // --- rooms --------------------------------------------------------------

    public function test_room_seeder_creates_the_confirmed_room_types_with_their_amenities(): void
    {
        $this->seed(RoomSeeder::class);

        $this->assertSame(6, Room::active()->count());
        $this->assertSame(2, Room::where('name', 'Single Room (VIP)')->count());
        $this->assertEqualsCanonicalizing(
            ['AC', 'Sofa', 'Table', 'Shoe Rack', 'Cupboard', 'Washroom'],
            Room::where('slug', 'deluxe-room')->first()->amenities->pluck('label')->all()
        );
        $this->assertSame('5 Beds', Room::where('slug', 'group-room')->value('bed_type'));

        // Client-confirmed nightly rates.
        $prices = Room::pluck('price_per_night', 'slug')->map(fn ($p) => (float) $p)->all();
        $this->assertSame([
            'package-room' => 1000.0, 'deluxe-room' => 2000.0, 'family-room' => 2500.0,
            'single-room-vip' => 1500.0, 'single-room-vip-2' => 1500.0, 'group-room' => 3000.0,
        ], $prices);

        $package = Room::where('slug', 'package-room')->first();
        $this->assertTrue($package->price_per_person);
        $this->assertSame([4, 5], [$package->min_guests, $package->max_guests]);
    }

    public function test_room_seeder_updates_existing_rooms_in_place_and_retires_placeholder_rooms_without_deleting(): void
    {
        $family = Room::factory()->create(['slug' => 'family-suite', 'name' => 'Family Suite', 'price_per_night' => 3500]);
        $executive = Room::factory()->create(['slug' => 'executive-room', 'is_active' => true]);
        Booking::factory()->for($executive)->create();
        Booking::factory()->for($family)->create();

        $this->seed(RoomSeeder::class);

        $family->refresh();
        $this->assertSame('Family Room', $family->name);
        $this->assertSame('family-room', $family->slug);
        $this->assertEquals(2500, (float) $family->price_per_night);
        $this->assertSame(1, $family->bookings()->count());
        $this->assertFalse($executive->fresh()->is_active);
        $this->assertSame(1, $executive->bookings()->count());
    }

    // --- per-person pricing (Package Room) -------------------------------------

    protected function packageRoom(): Room
    {
        return Room::factory()->create([
            'name' => 'Package Room', 'price_per_night' => 1000, 'price_per_person' => true,
            'min_guests' => 4, 'max_guests' => 5,
        ]);
    }

    public function test_package_room_is_charged_per_person_per_night(): void
    {
        $room = $this->packageRoom();

        $this->post(route('booking.store'), ['guests_label' => '5 Adults'] + $this->bookingPayload($room))
            ->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertEquals(5000, (float) $booking->nightly_rate);   // 1,000 × 5 guests
        $this->assertEquals(10000, (float) $booking->total_price);   // × 2 nights
    }

    public function test_package_room_only_accepts_groups_of_four_to_five(): void
    {
        $room = $this->packageRoom();

        foreach (['3 Adults', '6 Adults', 'Family'] as $label) {
            $this->post(route('booking.store'), ['guests_label' => $label] + $this->bookingPayload($room))
                ->assertSessionHasErrors('guests_label');
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_modifying_a_per_person_booking_reprices_it_for_the_new_party_size(): void
    {
        $room = $this->packageRoom();
        $booking = app(BookingService::class)->create($room, ['guests_label' => '4 Adults'] + $this->bookingPayload($room));
        $this->assertEquals(8000, (float) $booking->total_price);

        $modified = app(BookingService::class)->modify($booking, [
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'guests_label' => '5 Adults',
            'customer_name' => $booking->customer_name,
            'email' => $booking->email,
            'phone' => $booking->phone,
        ]);

        $this->assertEquals(10000, (float) $modified->total_price);
    }

    public function test_package_room_shows_its_per_person_price(): void
    {
        $this->seed(RoomSeeder::class);

        $this->get(route('rooms.index'))
            ->assertSeeText('NPR 1,000')
            ->assertSeeText('/ person / night')
            ->assertSeeText('For 4–5 guests');

        $this->get(route('booking'))->assertSeeText('Package Room — NPR 1,000 / person / night');
    }

    public function test_room_types_are_listed_once_with_all_their_amenities(): void
    {
        $this->seed(RoomSeeder::class);

        $response = $this->get(route('rooms.index'));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '>Single Room (VIP)</h3>'));
        $response->assertSeeText('2 rooms of this type');
        $response->assertSeeText('Shoe Rack');
        $response->assertSeeText('Clothes Hanger');
        $response->assertSeeText('NPR 1,500');
        $response->assertDontSeeText('King Bed');
    }

    public function test_a_room_without_a_price_cannot_be_booked(): void
    {
        $room = Room::factory()->create(['price_per_night' => null]);

        $this->get(route('rooms.show', $room->slug))->assertOk()->assertSeeText('Contact Us for Rates');

        $this->post(route('booking.store'), $this->bookingPayload($room))
            ->assertSessionHasErrors('room_id');
        $this->assertDatabaseCount('bookings', 0);

        $this->expectException(ValidationException::class);
        app(BookingService::class)->create($room, $this->bookingPayload($room));
    }

    public function test_booking_a_room_type_falls_back_to_a_free_room_of_the_same_type(): void
    {
        $vip1 = Room::factory()->create(['name' => 'Single Room (VIP)', 'price_per_night' => 2500, 'max_guests' => null]);
        $vip2 = Room::factory()->create(['name' => 'Single Room (VIP)', 'price_per_night' => 2500, 'max_guests' => null]);
        $payload = $this->bookingPayload($vip1);

        Booking::factory()->for($vip1)->create([
            'check_in' => $payload['check_in'], 'check_out' => $payload['check_out'], 'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->post(route('booking.store'), $payload)->assertRedirect();
        $this->assertSame($vip2->id, Booking::latest('id')->first()->room_id);

        // Both VIP rooms now taken: the type is full, no double booking.
        $this->post(route('booking.store'), $payload)->assertSessionHasErrors('check_in');
        $this->assertSame(2, Booking::count());
    }

    public function test_type_availability_check_considers_every_room_of_the_type(): void
    {
        $vip1 = Room::factory()->create(['name' => 'Single Room (VIP)', 'price_per_night' => 2500]);
        Room::factory()->create(['name' => 'Single Room (VIP)', 'price_per_night' => 2500]);
        $in = now()->addDays(5)->toDateString();
        $out = now()->addDays(7)->toDateString();
        Booking::factory()->for($vip1)->create(['check_in' => $in, 'check_out' => $out, 'status' => Booking::STATUS_CONFIRMED]);

        $this->getJson(route('rooms.availability', ['room' => $vip1->id, 'check_in' => $in, 'check_out' => $out]))
            ->assertJson(['available' => true]);
    }

    public function test_a_room_with_no_guest_limit_accepts_any_party_size(): void
    {
        $room = Room::factory()->create(['price_per_night' => 2000, 'max_guests' => null]);

        $this->assertFalse($room->exceedsCapacityFor('4 Adults'));
        $this->assertFalse($room->exceedsCapacityFor('Family'));
    }

    // --- about page -----------------------------------------------------------

    public function test_about_page_shows_only_confirmed_facilities_and_no_unconfirmed_claims(): void
    {
        $this->seed([SettingSeeder::class, RoomSeeder::class]);

        $response = $this->get(route('about'));

        $response->assertOk();
        // The About page tells the hotel's story (2026-10-04 brief); figures
        // and room listings live on the home and Rooms pages.
        $response->assertDontSeeText('Room Types');
        $response->assertDontSeeText('satisfied');
        // Facilities live on the Facilities page; About never names ones the
        // hotel confirmed it does NOT offer.
        $content = strstr(strstr($response->getContent(), 'id="about-hero"'), '<footer', true);
        foreach (['airport', 'fitness', 'gym', 'elevator', 'spa', 'wake-up'] as $claim) {
            $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($claim, '/').'\b/i', $content, "Unoffered facility: {$claim}");
        }
    }

    public function test_about_page_shows_no_guest_statistics(): void
    {
        $this->seed(SettingSeeder::class);

        $this->get(route('about'))
            ->assertOk()
            ->assertDontSeeText('guests served to date')
            ->assertDontSeeText('guests on a typical day');
    }

    // --- legacy cash bookings ---------------------------------------------------
    // Paying 100% at the hotel is no longer offered at checkout (hotel policy).
    // These tests switch the method back on only to keep the code path that
    // holds and settles bookings made with it before the change working.

    public function test_guest_can_choose_cash_on_arrival_which_holds_but_does_not_confirm_the_booking(): void
    {
        config(['payments.methods.cash.enabled' => true]);
        $booking = $this->pendingBooking();

        $this->post(route('booking.pay.continue', $booking->confirmation_code), ['payment_method' => 'cash'])
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        $payment = $booking->payments()->sole();
        $this->assertSame('cash', $payment->provider);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);

        $this->get(route('booking.confirmation', $booking->confirmation_code))
            ->assertSeeText('pay at the hotel');

        // Still held after the normal 30-minute gateway hold has passed.
        $this->travel(2)->hours();
        $this->assertFalse(app(BookingService::class)->isAvailable($booking->room_id, $booking->check_in->toDateString(), $booking->check_out->toDateString()));
        $this->assertFalse(app(BookingService::class)->expireIfAbandoned($booking));
    }

    public function test_cash_hold_expires_after_the_configured_window(): void
    {
        config(['payments.methods.cash.enabled' => true, 'payments.methods.cash.hold_hours' => 24]);
        $booking = $this->pendingBooking();
        $this->post(route('booking.pay.cash.initiate', $booking->confirmation_code));

        $this->travel(25)->hours();

        $this->assertTrue(app(BookingService::class)->expireIfAbandoned($booking));
    }

    public function test_staff_recording_cash_completes_the_guests_pending_cash_payment(): void
    {
        Mail::fake();
        config(['payments.methods.cash.enabled' => true]);
        $booking = $this->pendingBooking();
        $this->post(route('booking.pay.cash.initiate', $booking->confirmation_code));

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash']);

        $this->assertSame(1, $booking->payments()->count());
        $this->assertSame(Payment::STATUS_COMPLETED, $booking->payments()->sole()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    // --- Siddhartha Bank transfer ---------------------------------------------

    public function test_siddhartha_bank_transfer_is_offered_before_the_account_number_is_known(): void
    {
        Mail::fake();
        config(['payments.methods.bank_transfer.enabled' => true]);
        $this->seed(SettingSeeder::class);
        $booking = $this->pendingBooking();

        // Bank name only (as seeded): offered, and the guest is told details will follow.
        $this->get(route('booking.pay', $booking->confirmation_code))
            ->assertOk()
            ->assertSee('value="bank_transfer"', false)
            ->assertSeeText('Siddhartha Bank');

        $this->post(route('booking.pay.continue', $booking->confirmation_code), ['payment_method' => 'bank_transfer'])
            ->assertRedirect(route('booking.pay.bank.show', $booking->confirmation_code));

        $this->get(route('booking.pay.bank.show', $booking->confirmation_code))
            ->assertOk()
            ->assertSeeText('Our team will send you the Siddhartha Bank account details')
            ->assertSeeText('+977 9852690590');

        // Staff are reminded to send the details, then confirm once paid.
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $booking))
            ->assertSeeText('no account number is set in Settings yet');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'bank_transfer']);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_siddhartha_bank_transfer_works_end_to_end_with_only_an_account_number(): void
    {
        Mail::fake();
        config(['payments.methods.bank_transfer.enabled' => true]);
        $this->seed(SettingSeeder::class);
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking();

        // Admin enters just the account number (no account name/branch/QR yet).
        $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), [
            'site_phone' => '+977 9852690590',
            'site_email' => 'info@newwoodlandhotel.com',
            'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            'checkin_time' => '12:00',
            'checkout_time' => '10:00',
            'cancellation_window_hours' => 24,
            'bank_name' => 'Siddhartha Bank',
            'bank_account_number' => '01234567890',
        ])->assertSessionHasNoErrors();

        $this->get(route('booking.pay', $booking->confirmation_code))
            ->assertSee('value="bank_transfer"', false)
            ->assertSeeText('Siddhartha Bank');

        $this->post(route('booking.pay.continue', $booking->confirmation_code), ['payment_method' => 'bank_transfer'])
            ->assertRedirect(route('booking.pay.bank.show', $booking->confirmation_code));

        $this->get(route('booking.pay.bank.show', $booking->confirmation_code))
            ->assertOk()
            ->assertSeeText('Siddhartha Bank')
            ->assertSeeText('01234567890')
            ->assertDontSeeText('Account Name');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'bank_transfer', 'reference' => 'SBL-REF-1']);

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
        $this->assertSame('SBL-REF-1', $booking->payments()->sole()->reference_id);
    }

    // --- gateways ---------------------------------------------------------------

    public function test_esewa_is_refused_in_production_with_the_public_test_merchant_code(): void
    {
        config(['esewa.mode' => 'production', 'esewa.product_code' => 'EPAYTEST']);
        $this->assertFalse(app(EsewaPaymentService::class)->isAvailable());

        // A live merchant code with the public sandbox secret still left in place.
        config(['esewa.product_code' => 'REAL_MERCHANT', 'esewa.secret_key' => EsewaPaymentService::PUBLIC_SANDBOX_SECRET]);
        $this->assertFalse(app(EsewaPaymentService::class)->isAvailable());

        config(['esewa.secret_key' => 'real-live-secret']);
        $this->assertTrue(app(EsewaPaymentService::class)->isAvailable());
    }

    public function test_esewa_initiate_route_is_blocked_when_esewa_is_disabled(): void
    {
        config(['payments.methods.esewa.enabled' => false]);
        $booking = $this->pendingBooking();

        $this->post(route('booking.pay.initiate', $booking->confirmation_code))->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_lists_accepted_methods_in_order_when_configured(): void
    {
        config([
            'payments.methods.fonepay.enabled' => true,
            'fonepay.merchant_code' => 'TESTMERCHANT',
            'fonepay.secret_key' => 'test-secret',
            'payments.methods.bank_transfer.enabled' => true,
        ]);
        Setting::factory()->create(['bank_name' => 'Siddhartha Bank', 'bank_account_number' => '01234567890']);
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        $response->assertSeeInOrder(['value="esewa"', 'value="fonepay"', 'value="bank_transfer"'], false);
        $response->assertDontSee('value="cash"', false);
        $response->assertDontSee('Khalti');
    }
}
