<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every video is HLS on R2 and gets its duration when processed, so
     * progress is measured against that alone; the player's is not kept.
     */
    public function up(): void
    {
        Schema::table('video_progress', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('video_progress', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('last_position_seconds');
        });
    }
};
