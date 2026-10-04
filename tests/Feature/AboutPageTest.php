<?php

namespace Tests\Feature;

use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The About page (2026-10-04 brief): a nine-part story, only confirmed
 * facts, real Newwoodland photos or a branded placeholder — never a stock
 * photo presented as the hotel.
 */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        Storage::fake('public');
    }

    public function test_the_story_follows_the_agreed_order(): void
    {
        $this->get(route('about'))->assertOk()->assertSeeTextInOrder([
            'About Newwoodland', 'Where Comfort Meets Togetherness',
            'Located in Bhadrapur-8, Jhapa, Nepal, Newwoodland Hotel & Lounge is a family-friendly hotel',
            'Explore Newwoodland', 'Check Availability',
            'Our Story', 'Built Around Comfort, Leisure & Togetherness',
            'Our Approach', 'Hospitality That Begins With a Warm Welcome', '01', 'Comfort', '02', 'Care', '03', 'Cleanliness', '04', 'Warm Hospitality',
            'Our People', 'The People Behind Newwoodland', 'Ambika Pokharel', 'Founder', 'Raj Kumar Rai', 'Founder', 'Hem Bahadur Karki', 'Founder',
            'Our Commitment', 'Creating a Stay Worth Returning To', 'To Our Guests', 'To Our Team', 'To Our Property',
            'Welcome to Newwoodland', 'Your Time at Newwoodland, Your Way', 'Newwoodland welcomes you',
            'Explore Our Rooms', 'Check Availability',
        ]);
    }

    public function test_sections_not_in_the_approved_copy_are_absent(): void
    {
        $this->get(route('about'))
            ->assertDontSeeText('More Than a Place to Stay')
            ->assertDontSeeText('Hospitality Begins With People')
            ->assertDontSeeText('Stay. Relax. Connect.')
            ->assertDontSee('id="who-we-are"', false);
    }

    public function test_facilities_and_the_visual_strip_are_not_duplicated_here(): void
    {
        // Facilities have their own page (linked from the final CTA); the
        // client asked for both sections to be removed from About.
        $this->get(route('about'))
            ->assertDontSeeText('The Newwoodland Experience')
            ->assertDontSeeText('Everything You Need to Slow Down & Enjoy')
            ->assertDontSeeText('Comfortable Accommodation')
            ->assertDontSeeText('Family-Friendly Atmosphere')
            ->assertDontSeeText('Garden & Terrace')
            ->assertDontSeeText('A Place to Stay. A Place to Unwind. A Place to Connect.')
            ->assertDontSee('id="what-we-offer"', false)
            ->assertDontSee('id="discover-newwoodland"', false);
    }

    public function test_calls_to_action_lead_to_the_real_pages(): void
    {
        $this->get(route('about'))
            ->assertSee('href="'.route('rooms.index').'"', false)
            ->assertSee('href="'.route('booking').'"', false)
            ->assertSee('href="#our-story"', false)
            ->assertSee('id="our-story"', false);
    }

    public function test_nothing_unconfirmed_is_claimed(): void
    {
        $html = $this->get(route('about'))->getContent();
        $content = strstr(strstr($html, 'id="about-hero"'), '<footer', true);

        foreach (['founded', 'established in', 'since 19', 'since 20', 'award', 'best hotel', 'only hotel', 'luxury', '5-star', 'five-star', 'guests served'] as $claim) {
            $this->assertStringNotContainsStringIgnoringCase($claim, $content, "Unconfirmed claim on the About page: {$claim}");
        }
    }

    public function test_no_stock_photo_is_presented_as_the_hotel(): void
    {
        $html = $this->get(route('about'))->getContent();

        $this->assertStringNotContainsString('unsplash.com', $html);
        $this->assertStringNotContainsString('storage/site/', strstr(strstr($html, 'id="about-hero"'), '<footer', true));
        // Labelled placeholders stand in, naming the photo each slot needs.
        $this->assertStringContainsString('about-photo--placeholder', $html);
        $this->assertStringContainsString('Shared Hotel Spaces', $html);
        $this->assertStringContainsString('Wide Property View', $html);
        $this->assertStringContainsString('Client Photo Required', $html);
    }

    public function test_real_photos_replace_placeholders_without_code_changes(): void
    {
        // (File contents don't matter here — only that the client's file exists.)
        Storage::disk('public')->put('about/hero.jpg', 'jpeg-bytes');
        Storage::disk('public')->put('about/owners/raj-kumar-rai.jpg', 'jpeg-bytes');

        $this->get(route('about'))
            ->assertSee(Storage::disk('public')->url('about/hero.jpg'), false)
            ->assertSee(Storage::disk('public')->url('about/owners/raj-kumar-rai.jpg'), false)
            ->assertSee('alt="Portrait of Raj Kumar Rai"', false);
    }

    public function test_owner_cards_show_name_and_role_only_until_the_client_provides_more(): void
    {
        $response = $this->get(route('about'));
        $section = strstr(strstr($response->getContent(), 'id="about-owners"'), 'id="our-commitment"', true);

        $this->assertSame(3, substr_count($section, 'class="about-owner"'));
        $this->assertStringNotContainsString('about-owner__message', $section);
    }
}
