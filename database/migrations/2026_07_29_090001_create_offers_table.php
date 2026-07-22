<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A time-limited bundle of courses sold at one price. offer_starts_at/
     * offer_ends_at only gate WHEN a student may purchase — they have no
     * bearing on how long a purchased course stays open, which is
     * access_duration_days counted from the moment of purchase.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->dateTime('offer_starts_at');
            $table->dateTime('offer_ends_at');
            $table->unsignedInteger('access_duration_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
