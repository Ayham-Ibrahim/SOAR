<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lesson', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unique(['course_id', 'lesson_id']);
        });

        if (Schema::hasColumn('lessons', 'course_id')) {
            DB::table('lessons')
                ->whereNotNull('course_id')
                ->select(['id', 'course_id'])
                ->orderBy('id')
                ->chunkById(100, function ($lessons) {
                    $insert = [];

                    foreach ($lessons as $lesson) {
                        $insert[] = [
                            'course_id' => $lesson->course_id,
                            'lesson_id' => $lesson->id,
                        ];
                    }

                    DB::table('course_lesson')->insertOrIgnore($insert);
                });

            Schema::table('lessons', function (Blueprint $table) {
                $table->dropForeign(['course_id']);
                $table->dropColumn('course_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lesson');
    }
};
