<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Upsell;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the test owner
        $owner = User::where('email', 'owner@villa-upsell.com')->first();
        
        if (!$owner) {
            $this->command->error('Test owner not found. Please run AdminSeeder first.');
            return;
        }

        // Create test vendors (idempotent)
        $chef = Vendor::firstOrCreate(
            ['email' => 'chef.maria@example.com'],
            [
            'name' => 'Chef Maria',
            'whatsapp_number' => '+1234567890',
            'phone' => '+1234567890',
            'description' => 'Professional chef specializing in Mediterranean cuisine',
            'service_type' => 'chef',
            'is_active' => true,
        ]
        );

        $driver = Vendor::firstOrCreate(
            ['email' => 'transport@example.com'],
            [
            'name' => 'Airport Transport Service',
            'whatsapp_number' => '+1234567891',
            'phone' => '+1234567891',
            'description' => 'Reliable airport pickup and drop-off service',
            'service_type' => 'transport',
            'is_active' => true,
        ]
        );

        $cleaner = Vendor::firstOrCreate(
            ['email' => 'cleaning@example.com'],
            [
            'name' => 'Premium Cleaning Service',
            'whatsapp_number' => '+1234567892',
            'phone' => '+1234567892',
            'description' => 'Professional cleaning and housekeeping services',
            'service_type' => 'cleaning',
            'is_active' => true,
        ]
        );

        // Create test property
        $property = Property::create([
            'user_id' => $owner->id,
            'name' => 'Villa Paradise',
            'description' => 'Beautiful luxury villa with stunning ocean views',
            'instagram_url' => 'https://instagram.com/villaparadise',
            'hero_image_url' => 'https://example.com/villa-hero.jpg',
            'language' => 'en',
            'currency' => 'EUR',
            'access_token' => Str::uuid(),
            'tags' => ['luxury', 'ocean-view', 'family-friendly'],
        ]);

        // Create test upsells
        Upsell::create([
            'property_id' => $property->id,
            'primary_vendor_id' => $chef->id,
            'title' => 'Private Chef Service',
            'description' => 'Enjoy a gourmet meal prepared by our professional chef',
            'price' => 150.00,
            'category' => 'chef',
            'image_url' => 'https://example.com/chef-service.jpg',
            'availability_rules' => [
                'min_advance_hours' => 24,
                'max_guests' => 8,
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Upsell::create([
            'property_id' => $property->id,
            'primary_vendor_id' => $driver->id,
            'title' => 'Airport Pickup',
            'description' => 'Comfortable transfer from airport to villa',
            'price' => 75.00,
            'category' => 'transport',
            'image_url' => 'https://example.com/airport-pickup.jpg',
            'availability_rules' => [
                'min_advance_hours' => 2,
                'max_passengers' => 4,
            ],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Upsell::create([
            'property_id' => $property->id,
            'primary_vendor_id' => $cleaner->id,
            'title' => 'Extra Cleaning Service',
            'description' => 'Additional cleaning service during your stay',
            'price' => 50.00,
            'category' => 'cleaning',
            'image_url' => 'https://example.com/cleaning-service.jpg',
            'availability_rules' => [
                'min_advance_hours' => 12,
            ],
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $this->command->info('Test data created successfully!');
        $this->command->info('Property: ' . $property->name);
        $this->command->info('Check-in URL: ' . config('app.url') . '/checkin/' . $property->access_token);
    }
}