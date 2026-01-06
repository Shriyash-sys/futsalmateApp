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
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'email_otp',
                'email_otp_expires_at',
                'otp_resend_count',
                'otp_resend_expires_at',
                'email_verified_at'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('email_otp')->nullable()->after('remember_token');
            $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp');
            $table->unsignedInteger('otp_resend_count')->default(0)->after('email_otp_expires_at');
            $table->timestamp('otp_resend_expires_at')->nullable()->after('otp_resend_count');
        });
    }
};
