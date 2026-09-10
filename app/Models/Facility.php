<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Facility extends Model
{
    use HasFactory;

    /**
     * Hotel-wide facility categories. Distinct from room-level amenities
     * (see RoomAmenity), which describe what comes with a specific room
     * rather than what's available across the property.
     */
    public const CATEGORIES = [
        'connectivity' => 'Connectivity',
        'wellness_recreation' => 'Wellness & Recreation',
        'dining' => 'Dining',
        'guest_services' => 'Guest Services',
        'transportation_parking' => 'Transportation & Parking',
        'property_accessibility' => 'Property & Accessibility',
    ];

    public const OTHER_CATEGORY_LABEL = 'More Facilities';

    protected $fillable = ['icon', 'category', 'title', 'description', 'sort_order', 'show_on_homepage'];

    protected function casts(): array
    {
        return ['show_on_homepage' => 'boolean'];
    }

    public function scopeHomepage(Builder $query): Builder
    {
        return $query->where('show_on_homepage', true);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? self::OTHER_CATEGORY_LABEL;
    }
}
