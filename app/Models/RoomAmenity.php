<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAmenity extends Model
{
    protected $fillable = ['room_id', 'icon', 'label'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
