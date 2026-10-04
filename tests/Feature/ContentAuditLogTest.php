<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\GalleryImage;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Content and settings edits are written to the same audit trail as booking
 * and staff actions (AuditLogService), with before/after values for updates.
 */
class ContentAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
    }

    /**
     * A real 200x200 grayscale PNG built by hand, so the `image` and
     * `dimensions` rules pass without the GD extension (see AdminGalleryCrudTest).
     */
    protected function fakeRealImage(string $name, int $size = 200): UploadedFile
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\0".str_repeat("\x80", $size), $size);
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 0, 0, 0, 0))
            .$chunk('IDAT', gzcompress($rows))
            .$chunk('IEND', '');

        $path = tempnam(sys_get_temp_dir(), 'audit-test').'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    protected function lastLog(string $action): AuditLog
    {
        $log = AuditLog::where('action', $action)->latest('id')->first();
        $this->assertNotNull($log, "Expected an audit entry for {$action}.");

        return $log;
    }

    public function test_room_create_update_toggle_and_delete_are_logged_with_changes(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.rooms.store'), [
            'name' => 'Garden Suite', 'price_per_night' => 2800, 'max_guests' => 4, 'is_active' => '1',
        ]);
        $room = Room::firstWhere('name', 'Garden Suite');
        $created = $this->lastLog('room.created');
        $this->assertSame($this->admin->id, $created->admin_id);
        $this->assertSame('room', $created->target_type);
        $this->assertSame($room->id, $created->target_id);

        $this->actingAs($this->admin, 'admin')->put(route('admin.rooms.update', $room), [
            'name' => 'Garden Suite', 'slug' => $room->slug, 'price_per_night' => 3200, 'max_guests' => 4,
            'is_active' => '1', 'amenities' => ['Balcony'],
        ]);
        $updated = $this->lastLog('room.updated');
        $this->assertEquals(2800, $updated->meta['before']['price_per_night']);
        $this->assertEquals(3200, $updated->meta['after']['price_per_night']);
        $this->assertSame(['Balcony'], $updated->meta['amenities']['after']);
        $this->assertArrayNotHasKey('updated_at', $updated->meta['after']);

        $this->actingAs($this->admin, 'admin')->patch(route('admin.rooms.toggle-active', $room));
        $this->lastLog('room.deactivated');

        $this->actingAs($this->admin, 'admin')->delete(route('admin.rooms.destroy', $room));
        $this->assertSame($room->id, $this->lastLog('room.deleted')->target_id);
    }

    public function test_saving_a_room_without_changes_does_not_log_noise(): void
    {
        $room = Room::factory()->create(['price_per_night' => 1500, 'max_guests' => 2, 'is_active' => true, 'is_featured' => false, 'price_per_person' => false]);

        $this->actingAs($this->admin, 'admin')->put(route('admin.rooms.update', $room), [
            'name' => $room->name, 'slug' => $room->slug, 'room_number' => $room->room_number,
            'short_description' => $room->short_description, 'description' => $room->description,
            'price_per_night' => 1500, 'max_guests' => 2, 'min_guests' => $room->min_guests,
            'bed_type' => $room->bed_type, 'size_sqm' => $room->size_sqm, 'badge_label' => $room->badge_label,
            'is_active' => '1', 'availability_status' => $room->availability_status,
        ]);

        $this->assertSame(0, AuditLog::where('action', 'room.updated')->count());
    }

    public function test_room_image_changes_are_logged(): void
    {
        Storage::fake('public');
        $room = Room::factory()->create();

        $this->actingAs($this->admin, 'admin')->post(route('admin.rooms.images.store', $room), [
            'image' => $this->fakeRealImage('room.png'),
        ]);
        $this->assertSame($room->id, $this->lastLog('room.image_added')->target_id);

        $image = $room->images()->first();
        $this->actingAs($this->admin, 'admin')->delete(route('admin.rooms.images.destroy', [$room, $image]));
        $this->lastLog('room.image_deleted');
    }

    public function test_gallery_changes_are_logged(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin, 'admin')->post(route('admin.gallery.store'), [
            'title' => 'Lobby', 'category' => 'rooms', 'image' => $this->fakeRealImage('lobby.png'),
        ]);
        $image = GalleryImage::firstWhere('title', 'Lobby');
        $this->assertSame($image->id, $this->lastLog('gallery.created')->target_id);

        $this->actingAs($this->admin, 'admin')->put(route('admin.gallery.update', $image), ['title' => 'Main Lobby', 'category' => 'rooms']);
        $this->assertSame('Main Lobby', $this->lastLog('gallery.updated')->meta['after']['title']);

        $this->actingAs($this->admin, 'admin')->delete(route('admin.gallery.destroy', $image));
        $this->lastLog('gallery.deleted');
    }

    public function test_facility_faq_and_testimonial_changes_are_logged(): void
    {
        $facility = Facility::create(['icon' => 'bi-wifi', 'category' => 'transportation_parking', 'title' => 'Parking', 'description' => 'Free', 'sort_order' => 1]);
        $this->actingAs($this->admin, 'admin')->put(route('admin.facilities.update', $facility), [
            'icon' => 'bi-wifi', 'category' => 'transportation_parking', 'title' => 'Free Parking', 'description' => 'Free',
        ]);
        $this->assertSame('Parking', $this->lastLog('facility.updated')->meta['before']['title']);
        $this->actingAs($this->admin, 'admin')->delete(route('admin.facilities.destroy', $facility));
        $this->lastLog('facility.deleted');

        $this->actingAs($this->admin, 'admin')->post(route('admin.faqs.store'), ['question' => 'Parking?', 'answer' => 'Yes.']);
        $faq = Faq::firstWhere('question', 'Parking?');
        $this->assertSame($faq->id, $this->lastLog('faq.created')->target_id);
        $this->actingAs($this->admin, 'admin')->put(route('admin.faqs.update', $faq), ['question' => 'Parking?', 'answer' => 'Yes, free.']);
        $this->assertSame('Yes, free.', $this->lastLog('faq.updated')->meta['after']['answer']);

        $testimonial = Testimonial::create(['name' => 'Asha', 'location' => 'Jhapa', 'rating' => 5, 'content' => 'Lovely stay.']);
        $this->actingAs($this->admin, 'admin')->delete(route('admin.testimonials.destroy', $testimonial));
        $this->assertSame($testimonial->id, $this->lastLog('testimonial.deleted')->target_id);
    }

    public function test_settings_changes_including_bank_details_are_logged(): void
    {
        Setting::factory()->create(['bank_account_number' => '1111111111']);

        $this->actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
            'hotel_name' => 'Newwoodland Hotel and Lounge',
            'site_phone' => '+977 9852690590',
            'site_email' => 'info@newwoodlandhotel.com',
            'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            'checkin_time' => '12:00',
            'checkout_time' => '10:00',
            'cancellation_window_hours' => 24,
            'bank_name' => 'Siddhartha Bank',
            'bank_account_name' => 'Newwoodland Hotel and Lounge',
            'bank_account_number' => '2222222222',
        ])->assertSessionHasNoErrors();

        $log = $this->lastLog('settings.updated');
        $this->assertSame('1111111111', $log->meta['before']['bank_account_number']);
        $this->assertSame('2222222222', $log->meta['after']['bank_account_number']);
    }

    public function test_secret_looking_meta_keys_are_redacted(): void
    {
        $this->actingAs($this->admin, 'admin');

        $log = app(AuditLogService::class)->log('test.action', null, null, 'Testing.', [
            'password' => 'hunter2', 'nested' => ['api_token' => 'abc', 'safe' => 'ok'],
        ]);

        $this->assertSame('[redacted]', $log->meta['password']);
        $this->assertSame('[redacted]', $log->meta['nested']['api_token']);
        $this->assertSame('ok', $log->meta['nested']['safe']);
    }

    public function test_activity_log_page_is_for_admins_only(): void
    {
        app(AuditLogService::class)->log('faq.created', 'faq', 1, 'Created FAQ "Visible entry".');

        $this->actingAs($this->admin, 'admin')->get(route('admin.audit-logs.index'))
            ->assertOk()->assertSeeText('Visible entry');

        $this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')
            ->get(route('admin.audit-logs.index'))->assertForbidden();
    }
}
