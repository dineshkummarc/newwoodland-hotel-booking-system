<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAmenity extends Model
{
    protected $fillable = ['room_id', 'icon', 'label'];

    /**
     * Fallback icons (Bootstrap Icons) for common amenity labels, so an
     * amenity typed in on the admin room form gets a sensible icon without
     * staff having to pick one. Matched case-insensitively on whole words.
     */
    protected const ICONS = [
        'ac' => 'bi-snow',
        'air conditioning' => 'bi-snow',
        'tv' => 'bi-tv',
        'washroom' => 'bi-droplet',
        'bathroom' => 'bi-droplet',
        'cupboard' => 'bi-archive',
        'wardrobe' => 'bi-archive',
        'shoe rack' => 'bi-bookshelf',
        'sofa' => 'bi-lamp',
        'table' => 'bi-grid-3x2-gap',
        'hanger' => 'bi-bag',
        'bed' => 'bi-moon-stars',
        'beds' => 'bi-moon-stars',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function iconClass(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        $label = strtolower($this->label);

        foreach (self::ICONS as $keyword => $icon) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/', $label)) {
                return $icon;
            }
        }

        return 'bi-check-circle';
    }
}
