<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Payment;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PDOException;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * Phase 2 — double-booking prevention, modelled as closely as SQLite allows.
 *
 * What these tests prove: the ORDER of operations (room rows locked first,
 * in ascending id order; the final availability check inside the same
 * transaction; the insert last), that a booking committed by another guest
 * just before we got the lock is detected, that MySQL deadlocks / lock
 * timeouts are retried and finally reported as a friendly validation error
 * (never a 500), and that nothing partial is left behind.
 *
 * What they CANNOT prove: SQLite has no row locks (Laravel compiles
 * lockForUpdate() to nothing for it), so two truly simultaneous requests
 * are only exercised against real MySQL by MysqlBookingConcurrencyTest —
 * run it with MYSQL_CONCURRENCY_TEST_DATABASE set (see that class).
 */
class BookingConcurrencyTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected function data(array $overrides = []): array
    {
        return array_merge([
            'user_id' => null,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Guest',
            'email' => 'guest@example.com',
            'phone' => '+9779812345678',
            'country' => 'Nepal',
            'special_requests' => null,
        ], $overrides);
    }

    /** SQL without identifier quoting, so assertions hold on SQLite ("…") and MySQL (`…`). */
    protected function unquoted(string $sql): string
    {
        return str_replace(['"', '`'], '', $sql);
    }

    protected function deadlock(): QueryException
    {
        return new QueryException('mysql', 'insert into bookings ...', [], new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'));
    }

    public function test_room_rows_are_locked_in_id_order_before_the_availability_check_and_the_insert(): void
    {
        $roomA = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $roomB = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = ['sql' => $this->unquoted($query->sql), 'bindings' => $query->bindings];
        });

        // Primary room is the HIGHER id: the lock must still be in ascending order.
        app(BookingService::class)->create($roomB, $this->data(), [['room' => $roomA, 'guests_label' => '2 Adults']]);

        $lockIndex = collect($queries)->search(fn ($q) => str_contains($q['sql'], 'from rooms where id in'));
        $checkIndex = collect($queries)->search(fn ($q) => str_contains($q['sql'], 'from bookings') && str_contains($q['sql'], 'check_in <'));
        $insertIndex = collect($queries)->search(fn ($q) => str_starts_with($q['sql'], 'insert into bookings'));

        $this->assertNotFalse($lockIndex, 'The room rows must be locked.');
        $this->assertStringContainsString('order by id', $queries[$lockIndex]['sql']);
        if (DB::getDriverName() === 'mysql') {
            // SQLite compiles lockForUpdate() to nothing; MySQL must really lock.
            $this->assertStringContainsString('for update', $queries[$lockIndex]['sql']);
            $this->assertStringContainsString('for update', $queries[$checkIndex]['sql']);
        }
        $this->assertSame([$roomA->id, $roomB->id], array_map('intval', $queries[$lockIndex]['bindings']));
        $this->assertLessThan($checkIndex, $lockIndex, 'Rooms must be locked before availability is checked.');
        $this->assertLessThan($insertIndex, $checkIndex, 'Availability must be checked before the booking is inserted.');
    }

    public function test_a_guest_who_wins_the_room_first_is_detected_inside_the_transaction(): void
    {
        $room = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $data = $this->data();

        // Simulate the other guest's transaction committing in the instant
        // between our pre-checks and our acquiring the room lock.
        $injected = false;
        DB::listen(function ($query) use (&$injected, $room, $data) {
            if (! $injected && str_contains($this->unquoted($query->sql), 'from rooms where id in')) {
                $injected = true;
                Booking::factory()->for($room)->confirmed()->create(['check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'email' => 'winner@example.com']);
            }
        });

        try {
            app(BookingService::class)->create($room, $data);
            $this->fail('The second guest must not get the room.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('check_in', $e->errors());
            $this->assertStringContainsString('no longer available', $e->errors()['check_in'][0]);
        }

        $this->assertTrue($injected);
        // Our guest's booking was never written. (The "winner" row was
        // inserted on the same SQLite connection, so it rolls back with us
        // here; on MySQL it is another, already-committed transaction.)
        $this->assertFalse(Booking::where('email', 'guest@example.com')->exists());
    }

    public function test_a_deadlock_is_retried_and_the_booking_is_created_exactly_once(): void
    {
        $room = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);

        $failures = 1;
        Booking::creating(function () use (&$failures) {
            if ($failures-- > 0) {
                throw $this->deadlock();
            }
        });

        $booking = app(BookingService::class)->create($room, $this->data());

        $this->assertTrue($booking->exists);
        $this->assertSame(1, Booking::count());
    }

    public function test_persistent_lock_conflicts_end_in_a_friendly_error_not_a_500(): void
    {
        Mail::fake();
        $room = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        Booking::creating(fn () => throw $this->deadlock());

        $response = $this->post(route('booking.store'), [
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Guest',
            'email' => 'guest@example.com',
            'phone' => '9812345678',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('check_in');
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_a_failure_while_adding_extra_rooms_leaves_no_partial_booking(): void
    {
        $roomA = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $roomB = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);

        BookingRoom::creating(fn () => throw new \RuntimeException('database went away'));

        try {
            app(BookingService::class)->create($roomA, $this->data(), [['room' => $roomB, 'guests_label' => '2 Adults']]);
            $this->fail('Expected the failure to propagate.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, BookingRoom::count());
    }

    public function test_a_late_payment_never_takes_a_room_from_a_guest_holding_it_now(): void
    {
        Mail::fake();
        $room = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $dates = ['check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(7)->toDateString()];

        // Guest A's hold lapsed; guest B then legitimately holds the room.
        $late = Booking::factory()->for($room)->create($dates + ['status' => Booking::STATUS_PENDING, 'total_price' => 2000]);
        $late->forceFill(['created_at' => now()->subHour()])->save();
        $holder = Booking::factory()->for($room)->create($dates + ['status' => Booking::STATUS_PENDING]);

        $payment = Payment::factory()->for($late)->completed()->create(['amount' => 2000]);
        $confirmed = app(BookingService::class)->confirmAfterVerifiedPayment($payment);

        $this->assertFalse($confirmed);
        $this->assertSame(Booking::STATUS_PENDING, $late->refresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $holder->refresh()->status);
        $this->assertTrue($payment->refresh()->needsReview());
    }

    public function test_the_model_refuses_to_confirm_a_booking_onto_an_occupied_room_by_any_path(): void
    {
        $room = Room::factory()->create();
        $dates = ['check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(7)->toDateString()];
        Booking::factory()->for($room)->confirmed()->create($dates);
        $other = Booking::factory()->for($room)->cancelled()->create($dates);

        $this->expectException(ValidationException::class);

        // Bypasses every service — the model guard still refuses.
        $other->update(['status' => Booking::STATUS_CONFIRMED]);
    }
}
