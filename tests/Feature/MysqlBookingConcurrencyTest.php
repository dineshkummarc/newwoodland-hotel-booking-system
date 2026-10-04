<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * REAL MySQL double-booking test — two genuinely concurrent database
 * sessions (a second PHP process) racing for the same room. SQLite, which
 * the rest of the suite uses, has no row locks, so this is the only test
 * that exercises InnoDB's actual locking.
 *
 * Skipped unless pointed at a DEDICATED, THROWAWAY MySQL database (its
 * tables are dropped and re-created). The name must contain "test":
 *
 *   MYSQL_CONCURRENCY_TEST_DATABASE=newwoodland_concurrency_test \
 *   php artisan test --filter MysqlBookingConcurrencyTest
 *
 * Host/port/user/password come from the normal DB_* settings.
 */
class MysqlBookingConcurrencyTest extends TestCase
{
    protected string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) env('MYSQL_CONCURRENCY_TEST_DATABASE', '');

        if ($database === '') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_TEST_DATABASE to a throwaway MySQL database to run the real-locking tests.');
        }

        if (! str_contains(strtolower($database), 'test')) {
            $this->fail('Refusing to run: MYSQL_CONCURRENCY_TEST_DATABASE must name a throwaway database containing "test" (its tables are dropped).');
        }

        $this->database = $database;
        config([
            'database.connections.mysql_concurrency' => array_merge(config('database.connections.mysql'), ['database' => $database]),
            'database.default' => 'mysql_concurrency',
        ]);
        DB::purge('mysql_concurrency');
        Artisan::call('migrate:fresh', ['--force' => true]);
        BookingService::$baseTransactionLevel = 0;
    }

    protected function dsn(): string
    {
        $c = config('database.connections.mysql_concurrency');

        return "mysql:host={$c['host']};port={$c['port']};dbname={$this->database};charset=utf8mb4";
    }

    /**
     * Start a second process that locks the room row FOR UPDATE, waits, then
     * inserts its own confirmed booking for the same dates and commits —
     * i.e. another guest who reached the room first.
     */
    protected function startCompetingGuest(int $roomId, string $checkIn, string $checkOut, int $holdSeconds)
    {
        $script = tempnam(sys_get_temp_dir(), 'nwrace').'.php';
        file_put_contents($script, <<<'PHP'
<?php
[$_, $dsn, $user, $pass, $roomId, $in, $out, $hold] = $argv;
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$pdo->prepare('SELECT id FROM rooms WHERE id = ? FOR UPDATE')->execute([$roomId]);
fwrite(STDOUT, "locked\n"); fflush(STDOUT);
sleep((int) $hold);
$pdo->prepare("INSERT INTO bookings (confirmation_code, room_id, check_in, check_out, nights, guests_label, customer_name, email, phone, nightly_rate, total_price, status, created_at, updated_at)
               VALUES ('NW-RACE-WINNER', ?, ?, ?, 2, '2 Adults', 'Winner', 'winner@example.com', '+9779800000000', 1000, 2000, 'confirmed', NOW(), NOW())")
    ->execute([$roomId, $in, $out]);
$pdo->commit();
fwrite(STDOUT, "committed\n");
PHP);

        $c = config('database.connections.mysql_concurrency');
        $process = proc_open(
            [PHP_BINARY, $script, $this->dsn(), (string) $c['username'], (string) $c['password'], (string) $roomId, $checkIn, $checkOut, (string) $holdSeconds],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        $this->assertSame("locked\n", fgets($pipes[1]), 'The competing guest could not lock the room.');

        return [$process, $pipes, $script];
    }

    protected function booking(Room $room, string $in, string $out): Booking
    {
        return app(BookingService::class)->create($room, [
            'user_id' => null, 'check_in' => $in, 'check_out' => $out, 'guests_label' => '2 Adults',
            'customer_name' => 'Loser', 'email' => 'second@example.com', 'phone' => '+9779811111111',
        ]);
    }

    #[DataProvider('isolationLevels')]
    public function test_a_second_guest_waits_for_the_room_lock_and_then_sees_the_first_booking(string $isolation): void
    {
        $room = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $in = now()->addDays(5)->toDateString();
        $out = now()->addDays(7)->toDateString();

        DB::statement("SET SESSION TRANSACTION ISOLATION LEVEL {$isolation}");
        DB::statement('SET SESSION innodb_lock_wait_timeout = 10');

        [$process, $pipes, $script] = $this->startCompetingGuest($room->id, $in, $out, holdSeconds: 2);

        try {
            $this->booking($room, $in, $out);
            $this->fail('Both guests got the same room — DOUBLE BOOKING.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no longer available', $e->errors()['check_in'][0]);
        } finally {
            proc_close($process);
            @unlink($script);
        }

        $this->assertSame(1, Booking::where('room_id', $room->id)->count());
        $this->assertSame('winner@example.com', Booking::where('room_id', $room->id)->value('email'));
    }

    public function test_a_lock_that_is_held_too_long_ends_in_a_friendly_error_and_writes_nothing(): void
    {
        $room = Room::factory()->create(['price_per_night' => 1000, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $in = now()->addDays(5)->toDateString();
        $out = now()->addDays(7)->toDateString();

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        // Held well beyond 3 attempts × 1 second.
        [$process, $pipes, $script] = $this->startCompetingGuest($room->id, $in, $out, holdSeconds: 6);

        try {
            $this->booking($room, $in, $out);
            $this->fail('Expected a friendly "try again" error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('try again', $e->errors()['check_in'][0]);
        } finally {
            proc_close($process);
            @unlink($script);
        }

        $this->assertSame(0, Booking::where('email', 'second@example.com')->count());
    }

    public static function isolationLevels(): array
    {
        return [
            'REPEATABLE READ (MySQL default)' => ['REPEATABLE READ'],
            'READ COMMITTED (some managed hosts)' => ['READ COMMITTED'],
        ];
    }
}
