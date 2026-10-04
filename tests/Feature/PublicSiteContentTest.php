<?php

namespace Tests\Feature;

use Database\Seeders\RoomSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client-confirmed hotel information on the public site: official name,
 * owners, events, the "guests served" figure, and the privacy disclosures.
 */
class PublicSiteContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RoomSeeder::class]);
    }

    public function test_public_pages_use_the_official_hotel_name(): void
    {
        foreach (['home', 'about', 'rooms.index', 'facilities', 'gallery', 'contact', 'booking', 'privacy', 'terms', 'login', 'register'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString('Newwoodland Hotel &amp; Lounge', $html, "Official name missing on {$route}");
            $this->assertStringNotContainsString('Hotel and Lounge', $html, "Old name still on {$route}");
        }
    }

    public function test_about_page_lists_all_three_founders_with_confirmed_experience(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSeeTextInOrder([
                'Ambika Pokharel', 'Founder', '10 years of experience',
                'Raj Kumar Rai', 'Founder', '3+ years of experience',
                'Hem Bahadur Karki', 'Founder', '3+ years of experience',
            ]);
    }

    public function test_guests_served_figure_is_not_presented_as_satisfaction(): void
    {
        // (The About page no longer carries guest statistics — see AboutPageTest.)
        foreach (['home'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSeeText('90%+')
                ->assertSeeText('guests served to date')
                ->assertDontSeeText('satisfied');
        }
    }

    public function test_facilities_page_lists_the_confirmed_event_types_without_invented_details(): void
    {
        $response = $this->get(route('facilities'))->assertOk();

        $response->assertSeeText('Events & Celebrations')->assertSeeTextInOrder([
            'Family Gatherings', 'Business Meetings', 'Birthday Celebrations',
            'Pasni Celebrations', 'Get-Togethers', 'Wedding Ceremonies',
        ]);

        $events = strstr(strstr($response->getContent(), 'id="events"'), '</section>', true);
        foreach (['NPR', 'capacity', 'package', 'menu', 'hall'] as $invented) {
            $this->assertStringNotContainsStringIgnoringCase($invented, $events);
        }
    }

    public function test_privacy_policy_discloses_phone_verification_and_encrypted_id_numbers_but_no_scans(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSeeText('verify a phone number')
            ->assertSeeText('sent by SMS')
            ->assertSeeText('The ID number is stored encrypted')
            ->assertSeeText('Citizenship, Student ID or Office/Company ID')
            ->assertSeeText('Copies or scans of ID documents are not kept');
    }

    public function test_booking_page_states_the_photo_id_types_and_free_cancellation(): void
    {
        $this->get(route('booking'))
            ->assertOk()
            ->assertSeeText('Please bring a valid photo ID for check-in: Citizenship Certificate, Student ID Card or Office / Company ID Card.')
            ->assertSeeText('Free cancellation before arrival; 50% charge if the stay is cancelled after check-in.');
    }

    public function test_seeded_settings_carry_the_confirmed_policies(): void
    {
        $settings = \App\Models\Setting::first();

        $this->assertSame('Newwoodland Hotel & Lounge', $settings->hotel_name);
        $this->assertSame(0, (int) $settings->cancellation_deduction_percent);
        $this->assertSame(0, (int) $settings->cancellation_window_hours);
        $this->assertEquals(5, $settings->first_booking_discount_percent);
        $this->assertSame(50, config('bookings.after_arrival_cancellation_percent'));
    }
}
