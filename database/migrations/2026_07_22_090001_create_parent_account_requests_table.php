<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A student's request to create a parent account. The password is
     * validated and hashed at submission time, then copied as-is to the
     * `parents` row created on approval — the admin never sees or resets it.
     */
    public function up(): void
    {
        Schema::create('parent_account_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by_student_id')->constrained('users')->restrictOnDelete();
            $table->string('parent_name');
            $table->string('parent_phone');
            $table->string('password');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_parent_id')->nullable()->constrained('parents')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_account_requests');
    }
};
