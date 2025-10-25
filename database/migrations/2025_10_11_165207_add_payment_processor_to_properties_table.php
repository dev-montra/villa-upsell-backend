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
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'payment_processor')) {
                $table->enum('payment_processor', ['stripe', 'wise'])->default('stripe');
            }
            if (!Schema::hasColumn('properties', 'payout_schedule')) {
                $table->enum('payout_schedule', ['manual', 'weekly', 'monthly'])->default('manual');
            }
            if (!Schema::hasColumn('properties', 'wise_account_details')) {
                $table->json('wise_account_details')->nullable(); // Store bank details for Wise
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['payment_processor', 'payout_schedule', 'wise_account_details']);
        });
    }
};
