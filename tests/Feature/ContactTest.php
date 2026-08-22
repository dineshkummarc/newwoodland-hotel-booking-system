<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_contact_message_persists(): void
    {
        Mail::fake();
        Setting::factory()->create();

        $response = $this->post(route('contact.store'), [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'phone' => '9800000000',
            'subject' => 'Booking Question',
            'message' => 'Do you have availability next week?',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('contact_messages', [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'is_read' => false,
        ]);
    }

    public function test_invalid_contact_data_is_rejected(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
        $this->assertDatabaseCount('contact_messages', 0);
    }
}
