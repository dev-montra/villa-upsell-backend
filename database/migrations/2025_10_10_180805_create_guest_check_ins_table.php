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
        Schema::create('guest_check_ins', function (Blueprint $table) {
            $table->id();
            $table->string('access_token')->index();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone_number');
            $table->string('passport_url')->nullable();
            $table->timestamp('check_in_time');
            $table->json('additional_data')->nullable(); // For future extensibility
            $table->timestamps();
            
            // Index for faster lookups
            $table->index(['access_token', 'property_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_check_ins');
    }
};
