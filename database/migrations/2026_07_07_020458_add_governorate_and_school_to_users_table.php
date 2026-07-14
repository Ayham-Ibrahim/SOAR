<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purely informational profile fields (reports/statistics) — never used
     * to gate or filter content. Nullable and nullOnDelete so removing a
     * governorate/school never blocks or cascades onto a student account.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('governorate_id')->nullable()->after('phone')->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->after('governorate_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('governorate_id');
            $table->dropConstrainedForeignId('school_id');
        });
    }
};
