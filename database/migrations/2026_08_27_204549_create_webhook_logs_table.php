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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // e.g. Midtrans
            $table->string('event_id')->index(); // for idempotency
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->timestamp('received_at');
            $table->string('processing_status')->default('PENDING'); // PENDING, SUCCESS, FAILED
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
