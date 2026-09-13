<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The payment method the student transferred to, plus a copy of the
     * account details they were shown (payment_details) — so the request
     * still says where the money went after the admin edits or deletes that
     * method. Nullable only for requests sent before methods were required.
     */
    public function up(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('offer_id')->constrained()->nullOnDelete();
            $table->json('payment_details')->nullable()->after('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('payment_details');
        });
    }
};
