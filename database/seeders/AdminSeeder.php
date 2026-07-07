<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default admin account.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '+963999999999'],
            [
                'name' => 'Admin',
                'email' => 'admin@soar.com',
                'password' => 'password',
                'is_admin' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]
        );
    }
}
