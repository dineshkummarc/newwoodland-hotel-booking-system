<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMessageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_a_message_marks_it_read(): void
    {
        $admin = Admin::factory()->create();
        $message = ContactMessage::create([
            'name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Question about parking.', 'is_read' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.messages.show', $message));

        $response->assertOk();
        $response->assertSeeText('Question about parking.');
        $this->assertTrue($message->fresh()->is_read);
    }

    public function test_admin_can_delete_a_message(): void
    {
        $admin = Admin::factory()->create();
        $message = ContactMessage::create(['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Hi.']);

        $response = $this->actingAs($admin, 'admin')->delete(route('admin.messages.destroy', $message));

        $response->assertRedirect(route('admin.messages.index'));
        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_front_desk_cannot_view_messages(): void
    {
        $frontDesk = Admin::factory()->frontDesk()->create();
        $message = ContactMessage::create(['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Hi.']);

        $response = $this->actingAs($frontDesk, 'admin')->get(route('admin.messages.show', $message));

        $response->assertForbidden();
    }

    public function test_guest_cannot_view_messages(): void
    {
        $message = ContactMessage::create(['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Hi.']);

        $response = $this->get(route('admin.messages.show', $message));

        $response->assertRedirect(route('admin.login'));
    }
}
