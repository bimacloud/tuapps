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
        // 1. Rename activity_logs to audit_logs and add new columns
        Schema::rename('activity_logs', 'audit_logs');
        
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('module')->nullable()->after('action');
            $table->text('description')->nullable()->after('model_id');
            $table->string('user_agent')->nullable()->after('ip_address');
        });

        // 2. Modify approval_requests table
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropColumn('reference_id'); // Drop old specific reference
            $table->string('record_type')->after('type'); // polymorphic relation
            $table->unsignedBigInteger('record_id')->after('record_type'); // polymorphic relation
            $table->text('review_note')->nullable()->after('reason');
            $table->dateTime('reviewed_at')->nullable()->after('created_at');
        });

        // 3. Modify cashier_sessions
        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->decimal('expected_balance', 15, 2)->nullable()->after('ending_balance');
            $table->decimal('actual_balance', 15, 2)->nullable()->after('expected_balance');
            $table->decimal('difference', 15, 2)->nullable()->after('actual_balance');
            $table->text('notes')->nullable()->after('difference');
        });

        // 4. Modify payments status to string to easily accommodate 'CANCELLED' without enum alteration issues in sqlite
        // In sqlite, modifying enum directly might fail or lose data in some setups, we'll create a new column, copy data, and rename it.
        // Or simply just use Laravel's string type change if DBAL allows.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('status_new')->default('SUCCESS')->after('status');
        });

        // Copy data
        \Illuminate\Support\Facades\DB::statement("UPDATE payments SET status_new = status");

        // Drop old and rename
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('status_old')->default('SUCCESS')->after('status');
        });
        \Illuminate\Support\Facades\DB::statement("UPDATE payments SET status_old = status");
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('status_old', 'status');
        });

        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->dropColumn(['expected_balance', 'actual_balance', 'difference', 'notes']);
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropColumn(['record_type', 'record_id', 'review_note', 'reviewed_at']);
            $table->unsignedBigInteger('reference_id')->nullable();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['module', 'description', 'user_agent']);
        });

        Schema::rename('audit_logs', 'activity_logs');
    }
};
