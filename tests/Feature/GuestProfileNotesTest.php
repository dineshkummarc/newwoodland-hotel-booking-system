<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\GuestProfile;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestProfileNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function guestBooking(array $overrides = []): Booking
    {
        return Booking::factory()->for(Room::factory())->create(array_merge([
            'user_id' => null,
            'email' => 'Walkin@Example.com',
            'customer_name' => 'Walk In',
            'status' => Booking::STATUS_CONFIRMED,
        ], $overrides));
    }

    public function test_front_desk_can_mark_a_no_account_guest_vip_and_add_notes(): void
    {
        $this->guestBooking();
        $staff = Admin::factory()->frontDesk()->create();

        $this->actingAs($staff, 'admin')->put(route('admin.guests.update'), [
            'email' => 'walkin@example.com', 'is_vip' => '1', 'notes' => 'Prefers a quiet room.',
        ])->assertRedirect(route('admin.guests.show', ['email' => 'walkin@example.com']));

        $profile = GuestProfile::forEmail('WALKIN@example.com');
        $this->assertTrue($profile->is_vip);
        $this->assertSame('Prefers a quiet room.', $profile->notes);
        $this->assertSame($staff->id, $profile->updated_by);

        $this->actingAs($staff, 'admin')->get(route('admin.guests.show', ['email' => 'walkin@example.com']))
            ->assertOk()->assertSee('Prefers a quiet room.')->assertSee('VIP');
    }

    public function test_vip_and_notes_changes_are_audited_without_copying_note_content(): void
    {
        $this->guestBooking();
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->put(route('admin.guests.update'), [
            'email' => 'walkin@example.com', 'is_vip' => '1', 'notes' => 'Sensitive detail',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'guest.vip_changed', 'admin_id' => $admin->id]);
        $notesLog = AuditLog::firstWhere('action', 'guest.notes_updated');
        $this->assertNotNull($notesLog);
        $this->assertStringNotContainsString('Sensitive detail', $notesLog->description.json_encode($notesLog->meta));
    }

    public function test_vip_badge_and_notes_show_on_booking_page_and_front_desk(): void
    {
        $booking = $this->guestBooking(['check_in' => today()->toDateString(), 'check_out' => today()->addDays(2)->toDateString()]);
        GuestProfile::create(['email' => 'walkin@example.com', 'is_vip' => true, 'notes' => 'Allergic to feathers.']);
        $staff = Admin::factory()->frontDesk()->create();

        $this->actingAs($staff, 'admin')->get(route('admin.bookings.show', $booking))
            ->assertOk()->assertSee('VIP')->assertSee('Allergic to feathers.');

        $this->actingAs($staff, 'admin')->get(route('admin.front-desk.index'))
            ->assertOk()->assertSee('status vip', false);
    }

    public function test_notes_are_never_shown_to_the_customer(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        Booking::factory()->for(Room::factory())->create(['user_id' => $user->id, 'email' => 'member@example.com']);
        GuestProfile::create(['email' => 'member@example.com', 'is_vip' => true, 'notes' => 'Internal remark XYZ']);

        $this->actingAs($user)->get(route('account.bookings'))
            ->assertOk()->assertDontSee('Internal remark XYZ');
    }

    public function test_guest_profile_requires_staff_login_and_valid_input(): void
    {
        $this->guestBooking();

        $this->put(route('admin.guests.update'), ['email' => 'walkin@example.com', 'notes' => 'x'])
            ->assertRedirect(route('admin.login'));
        $this->get(route('admin.guests.show', ['email' => 'walkin@example.com']))
            ->assertRedirect(route('admin.login'));
        // A customer session is not a staff session.
        $this->actingAs(User::factory()->create())->get(route('admin.guests.show', ['email' => 'walkin@example.com']))
            ->assertRedirect(route('admin.login'));

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->put(route('admin.guests.update'), ['email' => 'walkin@example.com', 'notes' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('notes');
        // No profile is created for an email the hotel has never seen.
        $this->actingAs($admin, 'admin')->put(route('admin.guests.update'), ['email' => 'stranger@example.com', 'notes' => 'x'])
            ->assertNotFound();
        $this->assertDatabaseCount('guest_profiles', 0);
    }

    public function test_notes_are_escaped_when_displayed(): void
    {
        $this->guestBooking();
        GuestProfile::create(['email' => 'walkin@example.com', 'notes' => '<script>alert(1)</script>']);

        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.guests.show', ['email' => 'walkin@example.com']))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }
}
