<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('owner_name');
            }

            if (!Schema::hasColumn('vendors', 'profile_photo_url')) {
                $table->string('profile_photo_url')->nullable()->after('profile_photo_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'profile_photo_url')) {
                $table->dropColumn('profile_photo_url');
            }

            if (Schema::hasColumn('vendors', 'profile_photo_path')) {
                $table->dropColumn('profile_photo_path');
            }
        });
    }
};
