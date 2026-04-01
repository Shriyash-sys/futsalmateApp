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
        $hasReminder30 = Schema::hasColumn('books', 'reminder_30_sent');
        $hasReminder10 = Schema::hasColumn('books', 'reminder_10_sent');

        if (! $hasReminder30 && ! $hasReminder10) {
            return;
        }

        Schema::table('books', function (Blueprint $table) use ($hasReminder30, $hasReminder10) {
            if ($hasReminder30) {
                $table->dropColumn('reminder_30_sent');
            }

            if ($hasReminder10) {
                $table->dropColumn('reminder_10_sent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasReminder30 = Schema::hasColumn('books', 'reminder_30_sent');
        $hasReminder10 = Schema::hasColumn('books', 'reminder_10_sent');

        if ($hasReminder30 && $hasReminder10) {
            return;
        }

        Schema::table('books', function (Blueprint $table) use ($hasReminder30, $hasReminder10) {
            if (! $hasReminder30) {
                $table->boolean('reminder_30_sent')->default(false)->after('status');
            }

            if (! $hasReminder10) {
                $table->boolean('reminder_10_sent')->default(false)->after('reminder_30_sent');
            }
        });
    }
};
