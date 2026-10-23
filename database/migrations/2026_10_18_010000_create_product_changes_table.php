<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('request_hash', 64);
            $table->string('action', 32);
            $table->unsignedBigInteger('result_id');
            $table->json('before')->nullable();
            $table->json('after');
            $table->json('result');
            $table->timestamp('created_at');
            $table->unique(['actor_id', 'operation_id']);
            $table->index(['product_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_changes');
    }
};
