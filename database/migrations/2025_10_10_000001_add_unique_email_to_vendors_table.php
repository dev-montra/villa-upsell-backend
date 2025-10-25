<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate rows by email, keeping the lowest id
        DB::statement(<<<SQL
            DELETE FROM vendors v
            USING vendors d
            WHERE v.email = d.email
              AND v.id > d.id;
        SQL);

        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'email')) {
                return; // safety guard
            }
            // Add unique index if it doesn't already exist
            $table->unique('email', 'vendors_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique('vendors_email_unique');
        });
    }
};

