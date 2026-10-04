<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_full_access(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.rooms.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.settings.edit'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.reports.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.bookings.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.guests.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.front-desk.index'))->assertOk();
    }

    public function test_admin_role_has_full_access(): void
    {
        $admin = Admin::factory()->admin()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.rooms.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.gallery.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.facilities.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.messages.index'))->assertOk();
    }

    public function test_front_desk_can_manage_bookings_and_front_desk(): void
    {
        $admin = Admin::factory()->frontDesk()->create();
        $room = Room::factory()->create();
        $booking = Booking::factory()->for($room)->create(['status' => Booking::STATUS_CONFIRMED]);

        $this->actingAs($admin, 'admin')->get(route('admin.bookings.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $booking))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.front-desk.index'))->assertOk();
        $this->actingAs($admin, 'admin')->get(route('admin.guests.index'))->assertOk();
        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.check-in', $booking), ['id_verified' => '1'])->assertRedirect();
    }

    public function test_front_desk_is_refused_room_management(): void
    {
        $admin = Admin::factory()->frontDesk()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.rooms.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.rooms.create'))->assertForbidden();
    }

    public function test_front_desk_is_refused_gallery_facilities_and_testimonials(): void
    {
        $admin = Admin::factory()->frontDesk()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.gallery.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.facilities.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.testimonials.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.faqs.index'))->assertForbidden();
    }

    public function test_front_desk_is_refused_messages_and_settings(): void
    {
        $admin = Admin::factory()->frontDesk()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.messages.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_front_desk_is_refused_reports(): void
    {
        $admin = Admin::factory()->frontDesk()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($admin, 'admin')->get(route('admin.reports.revenue'))->assertForbidden();
    }

    public function test_all_roles_can_access_their_own_profile(): void
    {
        $frontDesk = Admin::factory()->frontDesk()->create();
        $superAdmin = Admin::factory()->create();

        $this->actingAs($frontDesk, 'admin')->get(route('admin.profile.edit'))->assertOk();
        $this->actingAs($superAdmin, 'admin')->get(route('admin.profile.edit'))->assertOk();
    }

    public function test_unauthenticated_visitor_is_redirected_from_every_role_gated_route(): void
    {
        $this->get(route('admin.rooms.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.front-desk.index'))->assertRedirect(route('admin.login'));
    }

    public function test_existing_admin_defaults_to_super_admin_after_migration(): void
    {
        // Simulates a pre-existing installation: the migration backfills
        // `role` with a DB default, so an admin created without an explicit
        // role still gets full access rather than being locked out.
        $admin = new Admin(['name' => 'Legacy Admin', 'email' => 'legacy@example.com', 'password' => 'secret']);
        $admin->save();

        $this->assertEquals(Admin::ROLE_SUPER_ADMIN, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->hasFullAccess());
    }
}
