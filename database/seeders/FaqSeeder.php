<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'What are the check-in and check-out times?',
                'answer' => 'Check-in begins at 12:00 PM and check-out is until 10:00 AM. Our front desk operates 24 hours for guest convenience.',
                'sort_order' => 1,
            ],
            [
                'question' => 'Do you provide free high-speed Wi-Fi?',
                'answer' => 'Yes! Complimentary high-speed Wi-Fi is available throughout the hotel, including all guest rooms, dining areas, and public lounges.',
                'sort_order' => 2,
            ],
            [
                'question' => 'Is breakfast included with the room rate?',
                'answer' => 'Selected room packages (such as Deluxe Room & Family Suite) include a complimentary breakfast buffet. Details are displayed during booking.',
                'sort_order' => 3,
            ],
            [
                'question' => 'Is airport transportation available from Bhadrapur Airport?',
                'answer' => 'The hotel does not operate its own airport shuttle, but Bhadrapur Airport is just a short distance away and taxis and local transport are readily available for a smooth ride to the hotel.',
                'sort_order' => 4,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(['question' => $faq['question']], $faq);
        }
    }
}
