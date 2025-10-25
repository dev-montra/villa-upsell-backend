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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'stripe_account_id')) {
                $table->string('stripe_account_id')->nullable();
            }
            if (!Schema::hasColumn('users', 'stripe_onboarding_completed')) {
                $table->boolean('stripe_onboarding_completed')->default(false);
            }
            if (!Schema::hasColumn('users', 'wise_account_id')) {
                $table->string('wise_account_id')->nullable();
            }
            if (!Schema::hasColumn('users', 'wise_onboarding_completed')) {
                $table->boolean('wise_onboarding_completed')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_account_id',
                'stripe_onboarding_completed',
                'wise_account_id',
                'wise_onboarding_completed',
            ]);
        });
    }
};