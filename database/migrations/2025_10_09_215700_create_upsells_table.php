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
        Schema::create('upsells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('primary_vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->foreignId('secondary_vendor_id')->nullable()->constrained('vendors')->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('category'); // e.g., 'chef', 'transport', 'cleaning', 'experience'
            $table->string('image_url')->nullable();
            $table->json('availability_rules')->nullable(); // Store availability rules as JSON
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upsells');
    }
};
