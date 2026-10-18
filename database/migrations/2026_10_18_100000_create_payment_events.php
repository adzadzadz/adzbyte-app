<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_event_id', 100)->unique();
            $table->string('type', 100);
            $table->boolean('livemode');
            $table->unsignedBigInteger('provider_created_at');
            $table->char('payload_hash', 64);
            $table->longText('evidence');
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('state', 24);
            $table->string('reason', 40)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at']);
        });
        Schema::create('payment_settlements', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_event_id')->unique()->constrained()->restrictOnDelete();
            $table->string('provider_payment_id', 100)->unique();
            $table->string('provider_intent_id', 100)->unique();
            $table->string('amount_minor', 30);
            $table->string('currency', 3);
            $table->boolean('livemode');
            $table->timestamp('paid_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (DB::table('payment_events')->exists() || DB::table('payment_settlements')->exists()) {
            throw new RuntimeException('Retain provider events and financial history. Use a forward repair or verified backup restore.');
        }
        Schema::dropIfExists('payment_settlements');
        Schema::dropIfExists('payment_events');
    }
};
