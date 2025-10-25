<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Link to the Owner
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('instagram_url')->nullable();
            
            // Branding and Configuration Fields
            $table->string('hero_image_url')->nullable(); // For the Hero Image/Video Upload
            $table->string('language', 10);              // e.g., 'en', 'es'
            $table->string('currency', 3);               // e.g., 'EUR', 'USD'
            
            // Unique Access Link - The core requirement
            $table->string('access_token')->unique(); // This is the 'tokenized link' segment
            
            // Property Tagging (for agencies managing multiple villas)
            $table->json('tags')->nullable(); // Stores tags as a JSON array
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
