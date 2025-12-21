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
            $table->string('email_otp')->nullable()->after('remember_token');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('email_otp')->nullable()->after('remember_token');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_otp', 'email_otp_expires_at']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['email_otp', 'email_otp_expires_at']);
        });
    }
};
