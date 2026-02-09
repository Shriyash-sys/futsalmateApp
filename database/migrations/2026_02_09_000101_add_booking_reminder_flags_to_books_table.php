<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('reminder_30_sent')->default(false)->after('status');
            $table->boolean('reminder_10_sent')->default(false)->after('reminder_30_sent');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['reminder_30_sent', 'reminder_10_sent']);
        });
    }
};

