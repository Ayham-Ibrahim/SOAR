<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * App Store review accounts (php artisan review:account): App Review signs
     * in from several devices, so these skip the one-session/one-device locks.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_reviewer')->default(false)->after('is_admin');
        });

        Schema::table('parents', function (Blueprint $table) {
            $table->boolean('is_reviewer')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_reviewer');
        });

        Schema::table('parents', function (Blueprint $table) {
            $table->dropColumn('is_reviewer');
        });
    }
};
