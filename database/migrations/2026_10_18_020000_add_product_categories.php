<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->boolean('limit_one_per_cart')->default(false);
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
        });
        Schema::create('product_category_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('request_hash', 64);
            $table->string('action', 16);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at');
            $table->unique(['actor_id', 'operation_id']);
            $table->index(['product_category_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_changes');
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
        Schema::dropIfExists('product_categories');
    }
};
