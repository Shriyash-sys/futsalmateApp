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
        Schema::table('communities', function (Blueprint $table) {
            $table->renameColumn('full_name', 'team_name');
            $table->renameColumn('location', 'preferred_courts');
            $table->dropColumn('members');
            $table->string('preferred_days')->nullable()->after('preferred_courts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->renameColumn('team_name', 'full_name');
            $table->renameColumn('preferred_courts', 'location');
            $table->integer('members')->default(0)->after('phone');
            $table->dropColumn('preferred_days');
        });
    }
};
