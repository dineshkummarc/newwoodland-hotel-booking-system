<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

class MultiRoomBookingTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected Room $deluxeA;

    protected Room $deluxeB;

    protected Room $suite;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::factory()->create(['first_booking_discount_percent' => null]);

        $this->deluxeA = Room::factory()->create(['name' => 'Deluxe Room', 'room_number' => '101', 'price_per_night' => 2000, 'price_per_person' => false, 'max_guests' => 2, 'min_guests' => null, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $this->deluxeB = Room::factory()->create(['name' => 'Deluxe Room', 'room_number' => '102', 'price_per_night' => 2000, 'price_per_person' => false, 'max_guests' => 2, 'min_guests' => null, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $this->suite = Room::factory()->create(['name' => 'Family Suite', 'room_number' => '201', 'price_per_night' => 3500, 'price_per_person' => false, 'max_guests' => 5, 'min_guests' => null, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
    }

    protected function data(array $overrides = []): array
    {
        return array_merge([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Group Leader', 'email' => 'group@example.com', 'phone' => '9800000000',
        ], $overrides);
    }

    protected function multi(array $overrides = []): Booking
    {
        return app(BookingService::class)->create($this->deluxeA, $this->data($overrides), [
            ['room' => $this->suite, 'guests_label' => '4 Adults'],
        ]);
    }

    public function test_one_booking_holds_several_rooms_with_a_combined_price(): void
    {
        $booking = $this->multi()->load('additionalRooms.room');

        $this->assertSame($this->deluxeA->id, $booking->room_id);
        $this->assertSame([$this->deluxeA->id, $this->suite->id], $booking->roomIds());
        $this->assertSame(2, $booking->roomCount());
        // 2 nights × (2,000 + 3,500)
        $this->assertSame(11000.0, (float) $booking->total_price);
        $line = BookingRoom::sole();
        $this->assertSame('4 Adults', $line->guests_label);
        $this->assertSame(7000.0, (float) $line->subtotal);
        $this->assertSame([4000.0, 7000.0], array_column($booking->roomLines(), 'subtotal'));
        $this->assertSame('Deluxe Room, Family Suite', $booking->roomNames());
    }

    public function test_every_room_is_checked_for_availability(): void
    {
        // The suite is already taken for those dates.
        Booking::factory()->for($this->suite)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->addDays(11)->toDateString(), 'check_out' => now()->addDays(13)->toDateString(),
        ]);

        try {
            $this->multi();
            $this->fail('Expected the booking to be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('additional_rooms', $e->errors());
        }

        // All-or-nothing: the free room was not booked on its own.
        $this->assertSame(1, Booking::count());
        $this->assertDatabaseCount('booking_rooms', 0);
    }

    public function test_an_additional_room_blocks_other_bookings_like_a_primary_room(): void
    {
        $this->multi();
        $service = app(BookingService::class);

        $this->assertFalse($service->isAvailable($this->suite->id, now()->addDays(11)->toDateString(), now()->addDays(14)->toDateString()));
        $this->expectException(ValidationException::class);
        $service->create($this->suite, $this->data(['email' => 'other@example.com', 'guests_label' => '3 Adults']));
    }

    public function test_cancelling_frees_every_room(): void
    {
        $booking = $this->multi();
        app(BookingService::class)->cancel($booking);

        $service = app(BookingService::class);
        $this->assertTrue($service->isAvailable($this->deluxeA->id, now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString()));
        $this->assertTrue($service->isAvailable($this->suite->id, now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString()));
    }

    public function test_guest_limits_are_enforced_per_room_and_rooms_must_be_distinct(): void
    {
        $service = app(BookingService::class);

        try {
            $service->create($this->deluxeA, $this->data(), [['room' => $this->deluxeB, 'guests_label' => '4 Adults']]);
            $this->fail('Expected a capacity error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('additional_rooms', $e->errors());
        }

        try {
            $service->create($this->deluxeA, $this->data(), [['room' => $this->deluxeA, 'guests_label' => '1 Adult']]);
            $this->fail('Expected a duplicate-room error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('additional_rooms', $e->errors());
        }

        $this->deluxeB->update(['availability_status' => Room::AVAILABILITY_MAINTENANCE]);
        $this->expectException(ValidationException::class);
        $service->create($this->deluxeA, $this->data(), [['room' => $this->deluxeB, 'guests_label' => '1 Adult']]);
    }

    public function test_payment_and_deposit_cover_the_whole_booking_and_confirm_all_rooms(): void
    {
        $booking = $this->multi();
        $booking->update(['deposit_percent' => 50]);
        $this->assertSame(5500.0, $booking->fresh()->amountDueNow());

        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 5500, 'status' => Payment::STATUS_COMPLETED, 'paid_at' => now()]);
        app(BookingService::class)->confirmAfterVerifiedPayment($payment);

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertSame(5500.0, $booking->outstandingBalance());
    }

    public function test_payment_does_not_confirm_if_any_room_was_lost(): void
    {
        $booking = $this->multi();
        // Someone else confirmed the suite in the meantime (e.g. after the hold lapsed).
        Booking::factory()->for($this->suite)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(11)->toDateString(),
        ]);

        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 11000, 'status' => Payment::STATUS_COMPLETED, 'paid_at' => now()]);
        app(BookingService::class)->confirmAfterVerifiedPayment($payment);

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_changing_dates_rechecks_and_reprices_every_room(): void
    {
        $booking = $this->multi();
        $service = app(BookingService::class);
        $payload = $this->data(['check_out' => now()->addDays(13)->toDateString()]);

        $booking = $service->modify($booking, $payload);
        $this->assertSame(16500.0, (float) $booking->total_price);
        $this->assertSame(10500.0, (float) BookingRoom::sole()->subtotal);

        Booking::factory()->for($this->suite)->create([
            'status' => Booking::STATUS_CONFIRMED,
            'check_in' => now()->addDays(13)->toDateString(), 'check_out' => now()->addDays(15)->toDateString(),
        ]);
        $this->expectException(ValidationException::class);
        $service->modify($booking, $this->data(['check_out' => now()->addDays(14)->toDateString()]));
    }

    public function test_check_in_and_out_apply_to_the_whole_party_and_reports_count_every_room(): void
    {
        $booking = $this->multi(['check_in' => today()->toDateString(), 'check_out' => today()->addDays(2)->toDateString()]);
        $booking->update(['status' => Booking::STATUS_CONFIRMED]);
        $service = app(BookingService::class);
        $service->checkIn($booking);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->assertViewHas('occupiedRooms', 2);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.occupancy', ['from' => today()->toDateString(), 'to' => today()->addDay()->toDateString()]))
            ->assertViewHas('occupiedRoomNights', 4);

        $performance = $this->actingAs($admin, 'admin')
            ->get(route('admin.reports.room-performance', ['from' => today()->toDateString(), 'to' => today()->addDay()->toDateString()]))
            ->viewData('rooms')->keyBy('id');
        $this->assertSame(4000.0, $performance[$this->deluxeA->id]->revenue);
        $this->assertSame(7000.0, $performance[$this->suite->id]->revenue);
        $this->assertSame(0.0, (float) $performance[$this->deluxeB->id]->revenue);

        $service->checkOut($booking);
        $this->assertSame(Booking::STATUS_CHECKED_OUT, $booking->fresh()->status);
    }

    public function test_public_form_books_several_rooms_of_the_chosen_type(): void
    {
        $this->post(route('booking.store'), $this->data(['room_id' => $this->deluxeA->id, 'rooms_count' => 2]))
            ->assertRedirect();

        $booking = Booking::sole();
        $this->assertEqualsCanonicalizing([$this->deluxeA->id, $this->deluxeB->id], $booking->roomIds());
        $this->assertSame(8000.0, (float) $booking->total_price);
    }

    public function test_public_form_refuses_more_rooms_than_are_free(): void
    {
        $this->post(route('booking.store'), $this->data(['room_id' => $this->deluxeA->id, 'rooms_count' => 3]))
            ->assertSessionHasErrors('rooms_count');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_staff_can_create_a_multi_room_walk_in_booking_with_blank_rows_ignored(): void
    {
        $this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')->post(route('admin.bookings.store'), $this->data([
            'room_id' => $this->deluxeA->id,
            'additional_rooms' => [
                ['room_id' => $this->suite->id, 'guests_label' => '4 Adults'],
                ['room_id' => '', 'guests_label' => ''],
            ],
        ]))->assertRedirect();

        $this->assertSame([$this->deluxeA->id, $this->suite->id], Booking::sole()->roomIds());
    }

    public function test_staff_form_validates_additional_rooms(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->post(route('admin.bookings.store'), $this->data([
            'room_id' => $this->deluxeA->id,
            'additional_rooms' => [['room_id' => $this->deluxeA->id, 'guests_label' => '1 Adult']],
        ]))->assertSessionHasErrors('additional_rooms.0.room_id');

        $this->actingAs($admin, 'admin')->post(route('admin.bookings.store'), $this->data([
            'room_id' => $this->deluxeA->id,
            'additional_rooms' => [['room_id' => $this->suite->id]],
        ]))->assertSessionHasErrors('additional_rooms.0.guests_label');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_an_additional_room_can_be_moved_and_cannot_collide_with_the_bookings_own_rooms(): void
    {
        $booking = $this->multi();
        $line = BookingRoom::sole();
        $admin = Admin::factory()->create();
        $bigRoom = Room::factory()->create(['name' => 'Family Suite', 'room_number' => '202', 'price_per_night' => 3500, 'max_guests' => 5, 'min_guests' => null, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);

        // Can't move it onto the booking's own primary room.
        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.reassign-room', $booking), ['room_id' => $this->deluxeA->id, 'booking_room_id' => $line->id])
            ->assertSessionHas('error');

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.reassign-room', $booking), ['room_id' => $bigRoom->id, 'booking_room_id' => $line->id])
            ->assertSessionHas('success');

        $this->assertSame($bigRoom->id, $line->fresh()->room_id);
        $this->assertSame($this->deluxeA->id, $booking->fresh()->room_id);
        $this->assertSame(11000.0, (float) $booking->fresh()->total_price, 'Moving rooms never changes the price.');
    }

    public function test_pages_and_invoice_list_every_room(): void
    {
        $booking = $this->multi();

        $this->get(route('booking.confirmation', $booking->confirmation_code))->assertOk()->assertSee('Deluxe Room, Family Suite');
        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.bookings.show', $booking))
            ->assertOk()->assertSee('Rooms (2)')->assertSee('Room 201');
        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.bookings.index'))
            ->assertOk()->assertSee('+ 1 more');

        $html = view('pdf.invoice', [
            'booking' => $booking->load(['room', 'additionalRooms.room', 'payments', 'charges', 'refunds']),
            'hotel' => Setting::first(), 'invoiceNumber' => 'INV-X', 'issuedAt' => now(),
        ])->render();
        $this->assertStringContainsString('Family Suite', $html);
        $this->assertStringContainsString('NPR 7,000', $html);
        $this->assertStringContainsString('NPR 11,000', $html);
    }

    public function test_a_room_used_as_an_additional_room_cannot_be_deleted(): void
    {
        $this->multi();

        $this->actingAs(Admin::factory()->create(), 'admin')->delete(route('admin.rooms.destroy', $this->suite))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('rooms', ['id' => $this->suite->id]);
    }

    public function test_single_room_bookings_are_unchanged(): void
    {
        $booking = app(BookingService::class)->create($this->deluxeA, $this->data());

        $this->assertFalse($booking->isMultiRoom());
        $this->assertSame('Deluxe Room', $booking->roomNames());
        $this->assertSame(4000.0, (float) $booking->total_price);
        $this->assertDatabaseCount('booking_rooms', 0);
    }
}
