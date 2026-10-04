<?php

namespace Tests\Feature;

use App\Mail\BookingCancelledAdminMail;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmationMail;
use App\Mail\NewBookingNotificationMail;
use App\Mail\NewContactMessageMail;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guest/admin notification emails go through the queue (database driver in
 * production), so mail-server latency or failure never reaches the booking
 * or payment response — and are only dispatched once the surrounding
 * transaction has committed.
 */
class QueuedMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
    }

    protected function sentMessages(): int
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->count();
    }

    public function test_notification_mailables_are_queued_after_commit(): void
    {
        foreach ([BookingConfirmationMail::class, NewBookingNotificationMail::class, BookingCancelledMail::class,
            BookingCancelledAdminMail::class, NewContactMessageMail::class] as $mailable) {
            $this->assertTrue(is_subclass_of($mailable, ShouldQueueAfterCommit::class), "{$mailable} should be queued after commit.");
        }
    }

    public function test_booking_emails_are_queued_then_delivered_by_the_worker(): void
    {
        $booking = Booking::factory()->for(Room::factory())->create(['status' => Booking::STATUS_CONFIRMED]);

        app(BookingService::class)->sendNotifications($booking, 'frontdesk@example.com');

        // Nothing is sent during the request itself — two jobs wait on the queue.
        $this->assertDatabaseCount('jobs', 2);
        $this->assertSame(0, $this->sentMessages());

        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(2, $this->sentMessages());
    }

    public function test_mail_is_dispatched_only_once_the_transaction_commits(): void
    {
        $booking = Booking::factory()->for(Room::factory())->create(['status' => Booking::STATUS_CONFIRMED]);

        DB::transaction(function () use ($booking) {
            app(BookingService::class)->sendCancellationNotifications($booking, 'frontdesk@example.com');

            // Still inside the transaction: nothing pushed yet.
            $this->assertDatabaseCount('jobs', 0);
        });

        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_mail_queued_inside_a_rolled_back_transaction_is_never_dispatched(): void
    {
        $booking = Booking::factory()->for(Room::factory())->create(['status' => Booking::STATUS_CONFIRMED]);

        try {
            DB::transaction(function () use ($booking) {
                app(BookingService::class)->sendCancellationNotifications($booking, 'frontdesk@example.com');

                throw new \RuntimeException('rolled back');
            });
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseCount('jobs', 0);
    }
}
