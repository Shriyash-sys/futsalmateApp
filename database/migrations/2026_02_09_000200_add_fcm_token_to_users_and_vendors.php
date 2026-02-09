<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'fcm_token')) {
                // Add at the end to avoid referencing non-existent columns
                $table->string('fcm_token')->nullable();
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'fcm_token')) {
                // Add at the end to avoid referencing non-existent columns
                $table->string('fcm_token')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'fcm_token')) {
                $table->dropColumn('fcm_token');
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'fcm_token')) {
                $table->dropColumn('fcm_token');
            }
        });
    }
};

