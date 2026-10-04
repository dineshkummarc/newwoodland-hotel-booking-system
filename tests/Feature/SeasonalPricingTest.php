<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomRate;
use App\Services\BookingService;
use App\Services\RoomPricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

class SeasonalPricingTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        // A fixed Monday so weekdays are predictable: 2030-01-07 is a Monday.
        $this->travelTo(Carbon::parse('2030-01-01 09:00'));
        $this->room = Room::factory()->create(['name' => 'Deluxe Room', 'price_per_night' => 2000, 'price_per_person' => false, 'max_guests' => 4, 'min_guests' => null]);
    }

    protected function rate(array $attributes): RoomRate
    {
        return RoomRate::create(array_merge([
            'name' => 'Rule', 'type' => RoomRate::TYPE_SEASON, 'room_type' => null,
            'adjustment' => RoomRate::ADJUSTMENT_PERCENT, 'value' => 0, 'is_active' => true,
        ], $attributes));
    }

    protected function quote(string $in, string $out, string $guests = '2 Adults'): array
    {
        return app(RoomPricingService::class)->quote($this->room, $guests, $in, $out);
    }

    protected function book(string $in, string $out, array $overrides = []): Booking
    {
        return app(BookingService::class)->create($this->room, array_merge([
            'check_in' => $in, 'check_out' => $out, 'guests_label' => '2 Adults',
            'customer_name' => 'Guest', 'email' => 'repeat@example.com', 'phone' => '9800000000',
        ], $overrides));
    }

    public function test_no_rules_means_the_flat_base_price_as_before(): void
    {
        $quote = $this->quote('2030-01-07', '2030-01-10');

        $this->assertSame(3, $quote['nights']);
        $this->assertSame(6000.0, $quote['subtotal']);
        $this->assertFalse($quote['varies']);
        $this->assertNull($this->book('2030-01-07', '2030-01-10')->price_breakdown);
    }

    public function test_weekend_rule_applies_only_to_the_chosen_nights(): void
    {
        $this->rate(['name' => 'Weekend', 'type' => RoomRate::TYPE_WEEKEND, 'days_of_week' => [5, 6], 'value' => 25]);

        // Thu, Fri, Sat nights (2030-01-10 is a Thursday).
        $quote = $this->quote('2030-01-10', '2030-01-13');

        $this->assertSame([2000.0, 2500.0, 2500.0], array_column($quote['nightly'], 'rate'));
        $this->assertSame(7000.0, $quote['subtotal']);
        $this->assertSame([null, 'Weekend', 'Weekend'], array_column($quote['nightly'], 'rule'));
    }

    public function test_precedence_is_holiday_then_season_then_weekend(): void
    {
        $this->rate(['name' => 'Weekend', 'type' => RoomRate::TYPE_WEEKEND, 'days_of_week' => [5, 6], 'value' => 10]);
        $this->rate(['name' => 'Winter', 'type' => RoomRate::TYPE_SEASON, 'start_date' => '2030-01-11', 'end_date' => '2030-01-31', 'adjustment' => 'fixed', 'value' => 3000]);
        $this->rate(['name' => 'Festival', 'type' => RoomRate::TYPE_HOLIDAY, 'start_date' => '2030-01-12', 'end_date' => '2030-01-12', 'adjustment' => 'fixed', 'value' => 5000]);

        // Thu (weekday, before season) / Fri (weekend + season) / Sat (all three) / Sun (season only)
        $quote = $this->quote('2030-01-10', '2030-01-14');

        $this->assertSame(['Base' => null, 'Fri' => 'Winter', 'Sat' => 'Festival', 'Sun' => 'Winter'], array_combine(['Base', 'Fri', 'Sat', 'Sun'], array_column($quote['nightly'], 'rule')));
        $this->assertSame([2000.0, 3000.0, 5000.0, 3000.0], array_column($quote['nightly'], 'rate'));
    }

    public function test_rules_respect_room_type_and_active_flag(): void
    {
        $this->rate(['name' => 'Other room', 'room_type' => 'Family Suite', 'start_date' => '2030-01-01', 'end_date' => '2030-12-31', 'value' => 50]);
        $this->rate(['name' => 'Inactive', 'start_date' => '2030-01-01', 'end_date' => '2030-12-31', 'value' => 50, 'is_active' => false, 'type' => RoomRate::TYPE_HOLIDAY]);

        $this->assertSame(4000.0, $this->quote('2030-03-01', '2030-03-03')['subtotal']);
    }

    public function test_fixed_rule_is_per_person_for_rooms_priced_per_person_and_percent_never_goes_negative(): void
    {
        $this->room->update(['price_per_person' => true, 'price_per_night' => 1000]);
        $this->rate(['name' => 'Peak', 'start_date' => '2030-02-01', 'end_date' => '2030-02-28', 'adjustment' => 'fixed', 'value' => 1500]);

        $this->assertSame(4500.0, $this->quote('2030-02-10', '2030-02-11', '3 Adults')['subtotal']);
        $this->assertSame(3000.0, $this->quote('2030-03-10', '2030-03-11', '3 Adults')['subtotal']);

        $rule = new RoomRate(['adjustment' => 'percent', 'value' => -150]);
        $this->assertSame(0.0, $rule->applyTo(1000, $this->room, 1));
    }

    public function test_booking_freezes_nightly_rates_and_discount_applies_to_the_seasonal_subtotal(): void
    {
        \App\Models\Setting::factory()->create(['first_booking_discount_percent' => 10]);
        app(\App\Services\SettingsService::class)->forget();
        $this->rate(['name' => 'Winter', 'start_date' => '2030-01-01', 'end_date' => '2030-01-31', 'adjustment' => 'fixed', 'value' => 3000]);

        $booking = $this->book('2030-01-07', '2030-01-09', ['email' => 'first@example.com']);

        $this->assertSame(2000.0, (float) $booking->nightly_rate, 'nightly_rate stays the base rate');
        $this->assertCount(2, $booking->price_breakdown);
        $this->assertSame(600.0, (float) $booking->discount_amount);
        $this->assertSame(5400.0, (float) $booking->total_price);
        $this->assertSame(6000.0, $booking->subtotal());
    }

    public function test_changing_or_deleting_rules_never_changes_existing_bookings(): void
    {
        $rule = $this->rate(['name' => 'Winter', 'start_date' => '2030-01-01', 'end_date' => '2030-01-31', 'adjustment' => 'fixed', 'value' => 3000]);
        $booking = $this->book('2030-01-07', '2030-01-09');
        $this->assertSame(6000.0, (float) $booking->total_price);

        $rule->update(['value' => 9000]);
        // A staff edit that keeps the same dates (e.g. fixing the guest's name) keeps the agreed price.
        app(BookingService::class)->modify($booking, [
            'check_in' => '2030-01-07', 'check_out' => '2030-01-09', 'guests_label' => '2 Adults',
            'customer_name' => 'Renamed Guest', 'email' => 'repeat@example.com', 'phone' => '9800000000',
        ]);
        $this->assertSame(6000.0, (float) $booking->fresh()->total_price);

        $rule->delete();
        $this->assertSame(6000.0, (float) $booking->fresh()->total_price);
    }

    public function test_changing_dates_reprices_the_new_nights_from_the_agreed_base_rate(): void
    {
        $booking = $this->book('2030-01-07', '2030-01-09');
        // The room's base price goes up after booking — the guest keeps 2,000.
        $this->room->update(['price_per_night' => 2600]);
        $this->rate(['name' => 'Festival', 'type' => RoomRate::TYPE_HOLIDAY, 'start_date' => '2030-01-09', 'end_date' => '2030-01-09', 'value' => 50]);

        $booking = app(BookingService::class)->modify($booking, [
            'check_in' => '2030-01-07', 'check_out' => '2030-01-10', 'guests_label' => '2 Adults',
            'customer_name' => 'Guest', 'email' => 'repeat@example.com', 'phone' => '9800000000',
        ]);

        // 2,000 + 2,000 + (2,000 + 50%)
        $this->assertSame(7000.0, (float) $booking->total_price);
        $this->assertSame('Festival', $booking->price_breakdown[2]['rule']);
    }

    public function test_overlapping_rules_of_the_same_type_are_rejected(): void
    {
        $admin = Admin::factory()->create();
        $this->rate(['name' => 'Winter', 'start_date' => '2030-01-01', 'end_date' => '2030-01-31', 'value' => 10]);

        $payload = ['name' => 'Overlap', 'type' => 'season', 'room_type' => '', 'start_date' => '2030-01-20', 'end_date' => '2030-02-10', 'adjustment' => 'percent', 'value' => 20, 'is_active' => '1'];

        $this->actingAs($admin, 'admin')->post(route('admin.room-rates.store'), $payload)->assertSessionHasErrors('start_date');
        $this->assertSame(1, RoomRate::count());

        // A different type may overlap — precedence decides.
        $this->actingAs($admin, 'admin')->post(route('admin.room-rates.store'), array_merge($payload, ['type' => 'holiday']))->assertSessionHasNoErrors();
        // Same type but not overlapping dates is fine.
        $this->actingAs($admin, 'admin')->post(route('admin.room-rates.store'), array_merge($payload, ['start_date' => '2030-02-01']))->assertSessionHasNoErrors();
        // An inactive rule never conflicts.
        $this->actingAs($admin, 'admin')->post(route('admin.room-rates.store'), array_merge($payload, ['is_active' => '0']))->assertSessionHasNoErrors();
    }

    public function test_weekend_rules_conflict_only_on_shared_days_and_rooms(): void
    {
        $pricing = app(RoomPricingService::class);
        $this->rate(['name' => 'Fri/Sat', 'type' => 'weekend', 'days_of_week' => [5, 6], 'value' => 10]);

        $this->assertCount(0, $pricing->conflictsFor(new RoomRate(['type' => 'weekend', 'days_of_week' => [7], 'is_active' => true])));
        $this->assertCount(1, $pricing->conflictsFor(new RoomRate(['type' => 'weekend', 'days_of_week' => [6, 7], 'is_active' => true])));
        $this->assertCount(1, $pricing->conflictsFor(new RoomRate(['type' => 'weekend', 'room_type' => 'Deluxe Room', 'days_of_week' => [5], 'is_active' => true])), 'An all-rooms rule overlaps every room type.');
    }

    public function test_rule_validation(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->post(route('admin.room-rates.store'), [
            'name' => '', 'type' => 'season', 'adjustment' => 'percent', 'value' => 900, 'room_type' => 'No Such Room',
        ])->assertSessionHasErrors(['name', 'start_date', 'end_date', 'value', 'room_type']);

        $this->actingAs($admin, 'admin')->post(route('admin.room-rates.store'), [
            'name' => 'Weekend', 'type' => 'weekend', 'adjustment' => 'fixed', 'value' => 0,
        ])->assertSessionHasErrors(['days_of_week', 'value']);

        $this->actingAs($admin, 'admin')->post(route('admin.room-rates.store'), [
            'name' => 'Backwards', 'type' => 'holiday', 'start_date' => '2030-05-10', 'end_date' => '2030-05-01', 'adjustment' => 'percent', 'value' => 10,
        ])->assertSessionHasErrors('end_date');
    }

    public function test_admin_can_manage_rules_and_changes_are_audited_but_front_desk_cannot(): void
    {
        $admin = Admin::factory()->admin()->create();

        $this->actingAs($admin, 'admin')->post(route('admin.room-rates.store'), [
            'name' => 'Weekend', 'type' => 'weekend', 'days_of_week' => ['5', '6'], 'adjustment' => 'percent', 'value' => 15, 'is_active' => '1', 'room_type' => 'Deluxe Room',
        ])->assertRedirect(route('admin.room-rates.index'));
        $rate = RoomRate::sole();
        $this->assertSame([5, 6], $rate->days_of_week);
        $this->assertNull($rate->start_date);
        $this->assertDatabaseHas('audit_logs', ['action' => 'room_rate.created', 'target_id' => $rate->id]);

        $this->actingAs($admin, 'admin')->get(route('admin.room-rates.index'))->assertOk()->assertSee('Weekend');

        $frontDesk = Admin::factory()->frontDesk()->create();
        $this->actingAs($frontDesk, 'admin')->get(route('admin.room-rates.index'))->assertForbidden();
        $this->actingAs($frontDesk, 'admin')->delete(route('admin.room-rates.destroy', $rate))->assertForbidden();

        $this->actingAs($admin, 'admin')->delete(route('admin.room-rates.destroy', $rate));
        $this->assertDatabaseCount('room_rates', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'room_rate.deleted']);
    }

    public function test_availability_endpoint_returns_a_server_side_estimate_with_rules(): void
    {
        $this->rate(['name' => 'Weekend', 'type' => 'weekend', 'days_of_week' => [5], 'value' => 50]);

        $this->getJson(route('rooms.availability', ['room' => $this->room->id, 'check_in' => '2030-01-10', 'check_out' => '2030-01-12', 'guests_label' => '2 Adults']))
            ->assertOk()
            ->assertJson(['available' => true, 'estimate' => ['nights' => 2, 'subtotal' => 5000, 'varies' => true]]);

        // Without a party size there is no estimate (per-person rooms need one).
        $this->getJson(route('rooms.availability', ['room' => $this->room->id, 'check_in' => '2030-01-10', 'check_out' => '2030-01-12']))
            ->assertJson(['estimate' => null]);
    }

    public function test_booking_pages_and_invoice_show_the_rate_breakdown(): void
    {
        $this->rate(['name' => 'Weekend', 'type' => 'weekend', 'days_of_week' => [5], 'value' => 50]);
        $booking = $this->book('2030-01-10', '2030-01-12');

        $this->get(route('booking.confirmation', $booking->confirmation_code))->assertOk()->assertSee('Weekend')->assertSee('NPR 3,000');
        $this->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('rates frozen at booking time');
    }
}
