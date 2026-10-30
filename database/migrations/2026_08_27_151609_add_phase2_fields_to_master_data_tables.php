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
        Schema::table('academic_years', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('name');
            $table->date('end_date')->nullable()->after('start_date');
        });

        Schema::table('majors', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('code'); // true = aktif
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('major_id')->constrained()->nullOnDelete();
            $table->string('homeroom_teacher')->nullable()->after('grade');
            $table->boolean('status')->default(true)->after('homeroom_teacher');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('relation')->nullable()->after('address'); // Ayah, Ibu, Wali
        });

        Schema::table('students', function (Blueprint $table) {
            $table->enum('gender', ['L', 'P'])->nullable()->after('name');
            $table->string('birth_place')->nullable()->after('gender');
            $table->date('birth_date')->nullable()->after('birth_place');
            $table->text('address')->nullable()->after('birth_date');
            $table->string('phone')->nullable()->after('address');
            $table->year('entry_year')->nullable()->after('status');
            $table->foreignId('major_id')->nullable()->after('entry_year')->constrained()->nullOnDelete();
            $table->string('photo')->nullable()->after('major_id');
            // guardian info is represented by guardian_id which already exists
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });

        Schema::table('majors', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['academic_year_id', 'homeroom_teacher', 'status']);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('relation');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['major_id']);
            $table->dropColumn(['gender', 'birth_place', 'birth_date', 'address', 'phone', 'entry_year', 'major_id', 'photo']);
        });
    }
};
