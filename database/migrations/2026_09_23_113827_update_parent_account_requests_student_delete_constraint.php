<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parent_account_requests', function (Blueprint $table) {
            $table->dropForeign(['requested_by_student_id']);
            $table->foreign('requested_by_student_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_account_requests', function (Blueprint $table) {
            $table->dropForeign(['requested_by_student_id']);
            $table->foreign('requested_by_student_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }
};
