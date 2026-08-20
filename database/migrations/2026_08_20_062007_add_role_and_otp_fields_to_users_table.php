<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->unique()->nullable()->after('name');
            $table->enum('role', ['super_admin', 'salon_owner', 'staff', 'customer'])->default('customer')->after('phone');
            $table->string('otp_code')->nullable()->after('role');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->timestamp('phone_verified_at')->nullable()->after('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'otp_code', 'otp_expires_at', 'phone_verified_at']);
        });
    }
};