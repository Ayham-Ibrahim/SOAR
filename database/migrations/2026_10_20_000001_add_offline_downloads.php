<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offline downloads of HLS videos.
     *
     * - videos.renditions: the heights ProcessVideoJob produced, in v0, v1…
     *   order, so a quality can be mapped to its playlist (null for videos
     *   processed before this column; read from master.m3u8 on first use).
     * - playback_sessions.purpose: "stream" or "download". Download sessions
     *   only exist to fetch the files and key; they don't count against the
     *   concurrent-streaming device limit.
     * - video_downloads: one offline copy per (student, video, device), with
     *   the date it stops playing unless the app syncs online again.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->json('renditions')->nullable()->after('height');
        });

        Schema::table('playback_sessions', function (Blueprint $table) {
            $table->string('purpose', 16)->default('stream')->after('device_id');
        });

        Schema::create('video_downloads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->unsignedInteger('height');
            $table->timestamp('offline_until');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'video_id', 'device_id']);
            $table->index(['user_id', 'offline_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_downloads');

        Schema::table('playback_sessions', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('renditions');
        });
    }
};
