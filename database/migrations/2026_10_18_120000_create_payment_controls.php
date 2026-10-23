<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_controls', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('payment_controls')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('request_hash', 64);
            $table->string('action', 16);
            $table->string('provider_session_id', 100)->nullable();
            $table->unsignedBigInteger('reserved_revision');
            $table->string('state', 16);
            $table->text('note');
            $table->string('failure_code', 40)->nullable();
            $table->text('proof')->nullable();
            $table->timestamps();
            $table->unique(['actor_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('payment_controls')->exists()) {
            throw new RuntimeException('Retain payment control commands and recovery history.');
        }
        Schema::dropIfExists('payment_controls');
    }
};
