<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('payment_event_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('request_hash', 64);
            $table->string('original_state', 24);
            $table->string('original_reason', 40)->nullable();
            $table->text('note');
            $table->text('verification');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['actor_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('payment_resolutions')->exists()) {
            throw new RuntimeException('Retain audited payment resolutions. Use a forward repair or verified backup restore.');
        }
        Schema::dropIfExists('payment_resolutions');
    }
};
