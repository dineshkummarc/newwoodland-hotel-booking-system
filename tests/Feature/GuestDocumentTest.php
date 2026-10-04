<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\GuestDocument;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

class GuestDocumentTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GuestDocument::DISK);
        config(['bookings.guest_documents' => ['enabled' => true, 'allow_upload' => true, 'max_upload_kb' => 5120]]);
        $this->booking = Booking::factory()->for(Room::factory())->create(['status' => Booking::STATUS_CHECKED_IN]);
    }

    protected function pdf(string $name = 'passport.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'doc').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    protected function record(Admin $staff, array $overrides = [])
    {
        return $this->actingAs($staff, 'admin')->post(route('admin.bookings.documents.store', $this->booking), array_merge([
            'document_type' => 'citizenship', 'document_number' => 'PA1234567', 'issuing_country' => 'Nepal',
        ], $overrides));
    }

    public function test_recording_is_on_by_default_without_uploads_and_can_be_switched_off(): void
    {
        // Hotel policy requires a photo ID at check-in: recording is on by default, scans are not.
        $defaults = require config_path('bookings.php');
        $this->assertTrue($defaults['guest_documents']['enabled']);
        $this->assertFalse($defaults['guest_documents']['allow_upload']);
        $this->assertTrue($defaults['checkin_requires_photo_id']);

        config(['bookings.guest_documents.enabled' => false]);

        $this->record(Admin::factory()->create())->assertNotFound();
        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.bookings.show', $this->booking))
            ->assertOk()->assertDontSee('Identity Documents');
    }

    public function test_front_desk_can_record_a_document_and_the_number_is_encrypted_at_rest(): void
    {
        $staff = Admin::factory()->frontDesk()->create();

        $this->record($staff, ['document_file' => $this->pdf()])->assertSessionHas('success');

        $document = GuestDocument::sole();
        $this->assertSame('PA1234567', $document->document_number);
        $this->assertSame($staff->id, $document->recorded_by);
        $raw = DB::table('guest_documents')->value('document_number');
        $this->assertStringNotContainsString('PA1234567', $raw);
        Storage::disk(GuestDocument::DISK)->assertExists($document->file_path);
        $this->assertStringStartsWith('guest-documents/', $document->file_path);
    }

    public function test_document_numbers_never_reach_the_audit_log_or_serialization(): void
    {
        $this->record(Admin::factory()->create());

        $log = AuditLog::firstWhere('action', 'booking.document_recorded');
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('PA1234567', $log->description.json_encode($log->meta));
        $this->assertArrayNotHasKey('document_number', GuestDocument::sole()->toArray());
    }

    public function test_booking_page_shows_only_the_masked_number(): void
    {
        $this->record(Admin::factory()->create());

        $this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')->get(route('admin.bookings.show', $this->booking))
            ->assertOk()->assertSee('••••4567')->assertDontSee('PA1234567');
    }

    public function test_file_type_size_and_number_format_are_validated(): void
    {
        $staff = Admin::factory()->create();

        $this->record($staff, ['document_file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')])
            ->assertSessionHasErrors('document_file');
        $this->record($staff, ['document_file' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')])
            ->assertSessionHasErrors('document_file');
        $this->record($staff, ['document_number' => '<script>', 'document_type' => 'bank_card'])
            ->assertSessionHasErrors(['document_number', 'document_type']);

        config(['bookings.guest_documents.allow_upload' => false]);
        $this->record($staff, ['document_file' => $this->pdf()])->assertSessionHasErrors('document_file');

        $this->assertDatabaseCount('guest_documents', 0);
    }

    public function test_file_is_only_downloadable_by_signed_in_staff(): void
    {
        $this->record(Admin::factory()->create(), ['document_file' => $this->pdf()]);
        $document = GuestDocument::sole();
        $url = route('admin.bookings.documents.download', [$this->booking, $document]);

        // Start from a genuinely signed-out client.
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get($url)->assertRedirect(route('admin.login'));

        $response = $this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')->get($url);
        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.document_viewed']);

        // Not through another booking's URL.
        $other = Booking::factory()->for(Room::factory())->create();
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.bookings.documents.download', [$other, $document]))->assertNotFound();
    }

    public function test_the_file_is_not_on_the_public_disk(): void
    {
        Storage::fake('public');
        $this->record(Admin::factory()->create(), ['document_file' => $this->pdf()]);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_only_admins_can_delete_and_deleting_removes_the_file(): void
    {
        $this->record(Admin::factory()->create(), ['document_file' => $this->pdf()]);
        $document = GuestDocument::sole();

        $this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')
            ->delete(route('admin.bookings.documents.destroy', [$this->booking, $document]))->assertForbidden();

        $this->actingAs(Admin::factory()->admin()->create(), 'admin')
            ->delete(route('admin.bookings.documents.destroy', [$this->booking, $document]))->assertSessionHas('success');

        $this->assertDatabaseCount('guest_documents', 0);
        Storage::disk(GuestDocument::DISK)->assertMissing($document->file_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.document_deleted']);
    }

    public function test_documents_never_appear_on_customer_pages(): void
    {
        $user = User::factory()->create();
        $this->booking->update(['user_id' => $user->id]);
        $this->record(Admin::factory()->create());

        $this->actingAs($user)->get(route('account.bookings'))->assertOk()->assertDontSee('••••4567')->assertDontSee('PA1234567');
        $this->get(route('booking.confirmation', $this->booking->confirmation_code))->assertOk()->assertDontSee('••••4567')->assertDontSee('PA1234567');
    }
}
