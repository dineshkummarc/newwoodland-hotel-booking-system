<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    use HasFactory;

    protected $fillable = ['path', 'title', 'category', 'sort_order'];

    /**
     * Alt text describing the photo's subject, e.g. "Newwoodland Hotel &
     * Lounge — Swimming Pool"; falls back to the category when untitled.
     */
    public function altText(): string
    {
        $subject = $this->title ?: ($this->category ? ucfirst($this->category) : null);

        return 'Newwoodland Hotel & Lounge'.($subject ? ' — '.$subject : '');
    }
}
