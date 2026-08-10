<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->unsignedInteger('subscription_days')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
            $table->unsignedInteger('subscription_days')->nullable(false)->change();
        });
    }
};
