<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The phase 3 migration widened payment_types.type only when the driver was "mysql",
     * so databases on the "mariadb" driver kept ENUM('BULANAN','BEBAS') and rejected SEMESTER/TAHUNAN/KHUSUS.
     */
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE payment_types MODIFY COLUMN type ENUM('BULANAN', 'SEMESTER', 'TAHUNAN', 'KHUSUS', 'BEBAS') NOT NULL");
        DB::table('payment_types')->where('type', 'BEBAS')->update(['type' => 'TAHUNAN']);
        DB::statement("ALTER TABLE payment_types MODIFY COLUMN type ENUM('BULANAN', 'SEMESTER', 'TAHUNAN', 'KHUSUS') NOT NULL");
    }

    /**
     * The widened enum is the intended schema; nothing to restore.
     */
    public function down(): void
    {
        //
    }
};
