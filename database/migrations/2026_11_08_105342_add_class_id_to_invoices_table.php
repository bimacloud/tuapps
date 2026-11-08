<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remember the class a student was in when the invoice was made, so class recaps of past
     * years still work after students move up a class.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('class_id')->nullable()->after('student_id')->constrained('classes')->nullOnDelete();
        });

        // Existing invoices: use the student's current class when that class belongs to the invoice's year.
        DB::table('invoices')->whereNull('class_id')->update([
            'class_id' => DB::raw('(SELECT students.class_id FROM students
                JOIN classes ON classes.id = students.class_id
                WHERE students.id = invoices.student_id
                AND (classes.academic_year_id = invoices.academic_year_id OR classes.academic_year_id IS NULL OR invoices.academic_year_id IS NULL))'),
        ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_id');
        });
    }
};
