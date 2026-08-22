<?php

namespace App\View\Composers;

use App\Models\ContactMessage;
use Illuminate\View\View;

class AdminNavComposer
{
    public function compose(View $view): void
    {
        $view->with('unreadMessagesCount', ContactMessage::where('is_read', false)->count());
    }
}
