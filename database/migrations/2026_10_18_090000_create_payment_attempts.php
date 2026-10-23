<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('amount_minor', 30);
            $table->string('currency', 3);
            $table->boolean('livemode');
            $table->string('state', 24);
            $table->string('provider_session_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->longText('provider_payload');
            $table->string('failure_code', 40)->nullable();
            $table->timestamps();
            $table->index(['state', 'updated_at']);
            $table->index(['order_id', 'created_at']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('payment_attempt_id')->nullable()->constrained('payment_attempts')->restrictOnDelete();
        });
        Schema::create('payment_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->foreignId('payment_attempt_id')->constrained()->restrictOnDelete();
            $table->char('request_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['actor_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('payment_attempts')->exists() || DB::table('payment_commands')->exists()) {
            throw new RuntimeException('Retain payment history. Use a forward repair or verified backup restore.');
        }
        Schema::dropIfExists('payment_commands');
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('payment_attempt_id'));
        Schema::dropIfExists('payment_attempts');
    }
};
