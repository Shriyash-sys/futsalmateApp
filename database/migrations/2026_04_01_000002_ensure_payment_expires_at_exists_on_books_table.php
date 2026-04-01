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
        if (!Schema::hasColumn('books', 'payment_expires_at')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dateTime('payment_expires_at')->nullable()->index()->after('payment_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('books', 'payment_expires_at')) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropIndex(['payment_expires_at']);
                $table->dropColumn('payment_expires_at');
            });
        }
    }
};
