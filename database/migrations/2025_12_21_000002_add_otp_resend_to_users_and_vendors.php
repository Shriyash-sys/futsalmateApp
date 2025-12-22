<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOtpResendToUsersAndVendors extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('otp_resend_count')->default(0)->after('email_otp_expires_at');
            $table->timestamp('otp_resend_expires_at')->nullable()->after('otp_resend_count');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->unsignedInteger('otp_resend_count')->default(0)->after('email_otp_expires_at');
            $table->timestamp('otp_resend_expires_at')->nullable()->after('otp_resend_count');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['otp_resend_count', 'otp_resend_expires_at']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['otp_resend_count', 'otp_resend_expires_at']);
        });
    }
}
