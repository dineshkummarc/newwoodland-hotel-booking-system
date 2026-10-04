<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'hotel_name' => 'Newwoodland Hotel and Lounge',
            'hotel_description' => 'A lovely place to stay.',
            'site_phone' => '+977 9852690590',
            'site_phone_secondary' => '+977 9801415167',
            'site_email' => 'info@newwoodlandhotel.com',
            'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            'checkin_time' => '12:00',
            'checkout_time' => '10:00',
            'cancellation_window_hours' => 24,
            'cancellation_policy_text' => 'Cancel at least 24 hours before check-in.',
            'bank_name' => 'Nepal Bank',
            'bank_account_name' => 'Newwoodland Hotel and Lounge',
            'bank_account_number' => '1234567890',
            'bank_branch' => 'Bhadrapur',
        ], $overrides);
    }

    public function test_admin_can_update_official_phone_numbers(): void
    {
        $admin = Admin::factory()->create();
        Setting::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), $this->validPayload());

        $response->assertRedirect(route('admin.settings.edit'));
        $setting = Setting::first();
        $this->assertEquals('+977 9852690590', $setting->site_phone);
        $this->assertEquals('+977 9801415167', $setting->site_phone_secondary);
    }

    public function test_updated_phone_number_reflects_on_public_contact_page(): void
    {
        $admin = Admin::factory()->create();
        Setting::factory()->create();

        $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), $this->validPayload([
            'site_phone' => '+977 9852690590',
        ]));

        $response = $this->get(route('contact'));

        $response->assertSee('9852690590');
    }

    public function test_admin_can_update_bank_transfer_details(): void
    {
        $admin = Admin::factory()->create();
        Setting::factory()->create();

        $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), $this->validPayload([
            'bank_name' => 'Everest Bank',
            'bank_account_number' => '99988877',
        ]));

        $setting = Setting::first();
        $this->assertEquals('Everest Bank', $setting->bank_name);
        $this->assertEquals('99988877', $setting->bank_account_number);
    }

    public function test_settings_update_requires_valid_data(): void
    {
        $admin = Admin::factory()->create();
        Setting::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), $this->validPayload([
            'site_email' => 'not-an-email',
        ]));

        $response->assertSessionHasErrors('site_email');
    }

    public function test_guest_cannot_update_settings(): void
    {
        $response = $this->put(route('admin.settings.update'), $this->validPayload());

        $response->assertRedirect(route('admin.login'));
    }

    public function test_front_desk_role_cannot_access_settings(): void
    {
        $admin = Admin::factory()->frontDesk()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.settings.edit'));

        $response->assertForbidden();
    }
}
