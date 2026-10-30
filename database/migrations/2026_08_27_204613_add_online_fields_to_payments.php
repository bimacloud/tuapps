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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_reference')->nullable()->after('transaction_number');
            $table->text('checkout_url')->nullable()->after('gateway_reference');
            // 'status' column is currently a string (SUCCESS, VOID, CANCELLED) due to Phase 6 modifications.
            // We just rely on it being a string so it can store PENDING, EXPIRED, FAILED.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway_reference', 'checkout_url']);
        });
    }
};
