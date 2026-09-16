<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An admin can revoke a grant before it expires. The row is kept for
     * audit — who revoked it, when and why — and stops opening content
     * (see Subscription::scopeActive).
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('is_active')->index();
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropIndex(['revoked_at']);
            $table->dropColumn(['revoked_at', 'revocation_reason']);
        });
    }
};
