<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The active grant that actually opens content. A student has access to
     * a course iff a row here has expires_at > now() — that comparison is
     * the single source of truth (see App\Services\CourseAccess). is_active
     * is refreshed nightly for reporting only; it never gates access.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->enum('source', ['direct', 'offer']);
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_request_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['student_id', 'course_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
