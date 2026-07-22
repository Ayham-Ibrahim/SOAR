<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * score is now computed from earned_points / total_points (weighted by
     * each question's `points`), not a flat correct/total ratio.
     * total_questions/correct_answers stay as informational counts.
     */
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->unsignedInteger('total_points')->nullable()->after('correct_answers');
            $table->unsignedInteger('earned_points')->nullable()->after('total_points');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['total_points', 'earned_points']);
        });
    }
};
