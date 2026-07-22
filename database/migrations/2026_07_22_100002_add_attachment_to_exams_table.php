<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The exam's own question material for a written exam (PDF or image) —
     * distinct from `submission_file` on exam_attempts, which is the
     * student's solution.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('attachment')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('attachment');
        });
    }
};
