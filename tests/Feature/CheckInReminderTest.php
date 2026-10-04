<?php

namespace Tests\Feature;

use App\Mail\CheckInReminderMail;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckInReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::factory()->create();
        config(['bookings.checkin_reminder' => ['enabled' => true, 'days_before' => 1, 'skip_if_booked_within_hours' => 12]]);
    }

    protected function booking(string $status, int $daysAhead, array $overrides = []): Booking
    {
        $booking = Booking::factory()->for(Room::factory())->create(array_merge([
            'status' => $status,
            'check_in' => today()->addDays($daysAhead)->toDateString(),
            'check_out' => today()->addDays($daysAhead + 2)->toDateString(),
        ], $overrides));
        // Made well before the reminder window.
        $booking->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();

        return $booking;
    }

    public function test_confirmed_guest_arriving_tomorrow_gets_one_queued_reminder(): void
    {
        $booking = $this->booking(Booking::STATUS_CONFIRMED, 1);

        $this->artisan('bookings:send-checkin-reminders')->assertSuccessful();

        Mail::assertQueued(CheckInReminderMail::class, fn ($mail) => $mail->hasTo($booking->email) && $mail->booking->is($booking));
        $this->assertNotNull($booking->fresh()->reminder_sent_at);
    }

    public function test_reminders_are_never_sent_twice(): void
    {
        $this->booking(Booking::STATUS_CONFIRMED, 1);

        $this->artisan('bookings:send-checkin-reminders');
        $this->artisan('bookings:send-checkin-reminders');

        Mail::assertQueued(CheckInReminderMail::class, 1);
    }

    public function test_only_confirmed_bookings_inside_the_window_are_reminded(): void
    {
        foreach ([Booking::STATUS_PENDING, Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW, Booking::STATUS_CHECKED_IN, Booking::STATUS_COMPLETED] as $status) {
            $this->booking($status, 1);
        }
        $this->booking(Booking::STATUS_CONFIRMED, 5);   // too far ahead
        $this->booking(Booking::STATUS_CONFIRMED, -1);  // already past

        $this->artisan('bookings:send-checkin-reminders');

        Mail::assertNotQueued(CheckInReminderMail::class);
    }

    public function test_a_booking_made_just_now_is_not_reminded_straight_after_its_confirmation(): void
    {
        $booking = $this->booking(Booking::STATUS_CONFIRMED, 1);
        $booking->forceFill(['created_at' => now()->subHour()])->saveQuietly();

        $this->artisan('bookings:send-checkin-reminders');

        Mail::assertNotQueued(CheckInReminderMail::class);
    }

    public function test_timing_is_configurable_and_reminders_can_be_switched_off(): void
    {
        $this->booking(Booking::STATUS_CONFIRMED, 3);

        $this->artisan('bookings:send-checkin-reminders');
        Mail::assertNotQueued(CheckInReminderMail::class);

        config(['bookings.checkin_reminder.days_before' => 3, 'bookings.checkin_reminder.enabled' => false]);
        $this->artisan('bookings:send-checkin-reminders');
        Mail::assertNotQueued(CheckInReminderMail::class);

        config(['bookings.checkin_reminder.enabled' => true]);
        $this->artisan('bookings:send-checkin-reminders');
        Mail::assertQueued(CheckInReminderMail::class, 1);
    }

    public function test_reminder_email_renders_the_stay_details(): void
    {
        $booking = $this->booking(Booking::STATUS_CONFIRMED, 1);

        $html = (new CheckInReminderMail($booking))->render();

        $this->assertStringContainsString($booking->confirmation_code, $html);
        $this->assertStringContainsString($booking->check_in->format('D, d M Y'), $html);
    }

    public function test_command_is_scheduled_once(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'bookings:send-checkin-reminders'));

        $this->assertCount(1, $events);
    }
}
