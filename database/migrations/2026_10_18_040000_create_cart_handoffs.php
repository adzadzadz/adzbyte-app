<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('cart_id')->constrained()->restrictOnDelete();
            $table->foreignId('issuer_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->uuid('operation_id');
            $table->char('authority_hash', 64);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cart_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_handoffs');
    }
};
