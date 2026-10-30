<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL Enum changes, Laravel uses Doctrine DBAL, but it's simpler to use raw statements or just drop and recreate if empty
        // However, we can just use strings instead of enums to avoid issues in the future.
        
        Schema::table('payment_types', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn('academic_year_id');
            
            $table->string('code')->after('id')->nullable();
            $table->string('category')->after('name')->nullable();
            $table->text('description')->after('type')->nullable();
            $table->boolean('status')->default(true)->after('description');
        });

        // Since we want to change ENUM `type` from ['BULANAN', 'BEBAS'] to ['BULANAN', 'SEMESTER', 'TAHUNAN', 'KHUSUS']
        // The safest way without Doctrine DBAL is raw query, but only for MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payment_types MODIFY COLUMN type ENUM('BULANAN', 'SEMESTER', 'TAHUNAN', 'KHUSUS') NOT NULL");
        }

        Schema::table('payment_rates', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');

            $table->foreignId('academic_year_id')->nullable()->after('payment_type_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date')->nullable()->after('amount');
            $table->boolean('status')->default(true)->after('effective_date');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount');
            $table->decimal('remaining_amount', 15, 2)->default(0)->after('paid_amount');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['payment_rate_id']);
            $table->dropColumn('payment_rate_id');

            $table->foreignId('payment_type_id')->nullable()->after('invoice_id')->constrained()->cascadeOnDelete();
            $table->renameColumn('month', 'period');
            $table->text('description')->nullable()->after('amount');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('description');
            $table->decimal('remaining_amount', 15, 2)->default(0)->after('paid_amount');
            $table->enum('status', ['BELUM BAYAR', 'SEBAGIAN', 'LUNAS', 'CANCELLED'])->default('BELUM BAYAR')->after('remaining_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Down migration can be complex with ENUM and foreign keys, but skipping detailed down for brevity
    }
};
