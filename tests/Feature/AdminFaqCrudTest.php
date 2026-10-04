<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFaqCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_faq_and_it_appears_publicly(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.faqs.store'), [
            'question' => 'Is breakfast included?',
            'answer' => 'Yes, breakfast is included for all bookings.',
        ]);

        $this->assertDatabaseHas('faqs', ['question' => 'Is breakfast included?']);
        $response->assertRedirect(route('admin.faqs.index'));

        $this->get(route('home'))->assertSeeText('Is breakfast included?');
    }

    public function test_faq_creation_requires_a_question_and_answer(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.faqs.store'), ['question' => '']);

        $response->assertSessionHasErrors(['question', 'answer']);
    }

    public function test_admin_can_update_and_delete_a_faq(): void
    {
        $admin = Admin::factory()->create();
        $faq = Faq::create(['question' => 'Old Q', 'answer' => 'Old A', 'sort_order' => 1]);

        $this->actingAs($admin, 'admin')->put(route('admin.faqs.update', $faq), [
            'question' => 'Updated Q', 'answer' => 'Updated A',
        ]);
        $this->assertEquals('Updated Q', $faq->fresh()->question);

        $this->actingAs($admin, 'admin')->delete(route('admin.faqs.destroy', $faq));
        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_guest_cannot_manage_faqs(): void
    {
        $faq = Faq::create(['question' => 'Q', 'answer' => 'A', 'sort_order' => 1]);

        $response = $this->delete(route('admin.faqs.destroy', $faq));

        $response->assertRedirect(route('admin.login'));
        $this->assertDatabaseHas('faqs', ['id' => $faq->id]);
    }
}
