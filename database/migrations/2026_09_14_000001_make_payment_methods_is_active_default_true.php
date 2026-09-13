<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A method with no status used to count as active and was shown to
     * students. Existing nulls keep that visible behaviour; from now on the
     * column is never null and a new method is active unless switched off.
     */
    public function up(): void
    {
        DB::table('payment_methods')->whereNull('is_active')->update(['is_active' => true]);

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_active')->nullable()->change();
        });
    }
};
