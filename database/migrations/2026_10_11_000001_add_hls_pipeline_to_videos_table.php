<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A third video source: uploaded straight to R2 and served as encrypted
     * HLS (source = hls). processing_status tracks the pipeline
     * (uploading → processing → ready | failed); the existing status column
     * stays the admin's completed/incomplete content flag.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->after('lesson_id')->constrained('users')->nullOnDelete();
            $table->string('processing_status')->nullable()->after('status')->index();
            $table->string('original_filename')->nullable();
            $table->string('original_key')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('content_type')->nullable();
            $table->string('upload_id', 1024)->nullable();
            $table->unsignedInteger('part_size')->nullable();
            $table->unsignedInteger('total_parts')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('thumbnail_key')->nullable();
            $table->text('encryption_key')->nullable();
            $table->timestamp('hls_ready_at')->nullable();
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropIndex(['processing_status']);
            $table->dropColumn([
                'processing_status',
                'original_filename',
                'original_key',
                'size',
                'content_type',
                'upload_id',
                'part_size',
                'total_parts',
                'height',
                'thumbnail_key',
                'encryption_key',
                'hls_ready_at',
                'error',
            ]);
        });
    }
};
