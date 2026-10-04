<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFacilityCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_facility_and_it_appears_on_the_public_site(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.facilities.store'), [
            'title' => 'Rooftop Pool',
            'category' => 'wellness_recreation',
            'show_on_homepage' => '1',
        ]);

        $facility = Facility::firstWhere('title', 'Rooftop Pool');
        $this->assertNotNull($facility);
        $response->assertRedirect(route('admin.facilities.index'));

        $public = $this->get(route('facilities'));
        $public->assertSeeText('Rooftop Pool');
    }

    public function test_facility_creation_validates_the_category(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.facilities.store'), [
            'title' => 'Bad Category',
            'category' => 'not-a-real-category',
        ]);

        $response->assertSessionHasErrors('category');
        $this->assertDatabaseMissing('facilities', ['title' => 'Bad Category']);
    }

    public function test_admin_can_delete_a_facility(): void
    {
        $admin = Admin::factory()->create();
        $facility = Facility::create(['title' => 'Old Facility', 'category' => 'dining', 'sort_order' => 1]);

        // destroy() redirects back() (to whatever page the delete was submitted from).
        $response = $this->actingAs($admin, 'admin')->delete(route('admin.facilities.destroy', $facility));

        $response->assertRedirect();
        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    }

    public function test_front_desk_cannot_manage_facilities(): void
    {
        $frontDesk = Admin::factory()->frontDesk()->create();

        $response = $this->actingAs($frontDesk, 'admin')
            ->post(route('admin.facilities.store'), ['title' => 'Hacked', 'category' => 'dining']);

        $response->assertForbidden();
        $this->assertDatabaseMissing('facilities', ['title' => 'Hacked']);
    }

    public function test_guest_cannot_manage_facilities(): void
    {
        $facility = Facility::create(['title' => 'Gym', 'category' => 'wellness_recreation', 'sort_order' => 1]);

        $response = $this->delete(route('admin.facilities.destroy', $facility));

        $response->assertRedirect(route('admin.login'));
        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
    }
}
