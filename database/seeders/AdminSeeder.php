<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@villa-upsell.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create Test Owner user
        User::create([
            'name' => 'Test Owner',
            'email' => 'owner@villa-upsell.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->command->info('Admin and test owner users created successfully!');
        $this->command->info('Admin: admin@villa-upsell.com / password123');
        $this->command->info('Owner: owner@villa-upsell.com / password123');
    }
}