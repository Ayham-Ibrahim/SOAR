<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Videos are YouTube links now. The uploaded-file path is kept intact
     * (behind config('video.uploads_enabled')), so url stays and simply
     * becomes optional; every existing row keeps source = upload.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('source')->default('upload')->after('lesson_id');
            $table->string('youtube_video_id')->nullable()->after('url');
            $table->string('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('videos')->whereNull('url')->update(['url' => '']);

        Schema::table('videos', function (Blueprint $table) {
            $table->string('url')->nullable(false)->change();
            $table->dropColumn(['source', 'youtube_video_id']);
        });
    }
};
