<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@newwoodlandhotel.com';

        if (Admin::where('email', $email)->exists()) {
            $this->command?->warn("Admin account already exists ({$email}) - skipping credential generation.");

            return;
        }

        $password = Str::password(16);

        Admin::create([
            'name' => 'Site Administrator',
            'email' => $email,
            'password' => $password,
        ]);

        $this->command?->info('==================================================');
        $this->command?->info(' Admin account created');
        $this->command?->info(' Email:    '.$email);
        $this->command?->info(' Password: '.$password);
        $this->command?->info(' Store these credentials securely. This password');
        $this->command?->info(' will not be shown again.');
        $this->command?->info('==================================================');
    }
}
