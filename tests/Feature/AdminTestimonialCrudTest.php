<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTestimonialCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_testimonial_and_it_appears_publicly(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.testimonials.store'), [
            'name' => 'Happy Guest',
            'rating' => 5,
            'content' => 'Wonderful stay, highly recommend.',
        ]);

        $this->assertDatabaseHas('testimonials', ['name' => 'Happy Guest']);
        $response->assertRedirect(route('admin.testimonials.index'));

        $this->get(route('home'))->assertSeeText('Wonderful stay, highly recommend.');
    }

    public function test_testimonial_rating_must_be_between_one_and_five(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.testimonials.store'), [
            'name' => 'Guest', 'rating' => 6, 'content' => 'Great!',
        ]);

        $response->assertSessionHasErrors('rating');
    }

    public function test_admin_can_delete_a_testimonial(): void
    {
        $admin = Admin::factory()->create();
        $testimonial = Testimonial::create(['name' => 'Guest', 'rating' => 5, 'content' => 'Nice.']);

        $this->actingAs($admin, 'admin')->delete(route('admin.testimonials.destroy', $testimonial));

        $this->assertDatabaseMissing('testimonials', ['id' => $testimonial->id]);
    }

    public function test_front_desk_cannot_manage_testimonials(): void
    {
        $frontDesk = Admin::factory()->frontDesk()->create();

        $response = $this->actingAs($frontDesk, 'admin')->post(route('admin.testimonials.store'), [
            'name' => 'Guest', 'rating' => 5, 'content' => 'Nice.',
        ]);

        $response->assertForbidden();
    }
}
