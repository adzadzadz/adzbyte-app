<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('credential_hash', 64)->nullable();
            $table->uuid('creation_operation_id')->unique();
            $table->char('creation_request_hash', 64);
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('quoted_price_id')->constrained('product_prices')->restrictOnDelete();
            $table->foreignId('quoted_terms_id')->constrained('product_terms')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });
        Schema::create('cart_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('request_hash', 64);
            $table->string('action', 16);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cart_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_changes');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
