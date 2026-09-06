<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exam_attempts', 'submission_file')) {
            Schema::table('exam_attempts', function (Blueprint $table) {
                $table->dropColumn('submission_file');
            });
        }
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('submission_file')->nullable()->after('earned_points');
        });
    }
};
