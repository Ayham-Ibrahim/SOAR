<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a student or parent last looked at a section of the app (news,
     * exam results), so anything newer can be flagged as new. A parent has
     * one results marker per child; student_id is 0 where it doesn't apply,
     * so the unique key also holds there (MySQL lets NULLs repeat).
     */
    public function up(): void
    {
        Schema::create('seen_markers', function (Blueprint $table) {
            $table->id();
            $table->morphs('viewer');
            $table->string('section');
            $table->unsignedBigInteger('student_id')->default(0);
            $table->timestamp('seen_at');
            $table->timestamps();

            $table->unique(['viewer_type', 'viewer_id', 'section', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seen_markers');
    }
};
