<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedInteger('version')->unique();
            $table->json('content');
            $table->char('content_hash', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id')->unique();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('commerce_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('current_version_id')->nullable()->constrained('commerce_policy_versions')->restrictOnDelete();
        });
        DB::table('commerce_settings')->insert(['id' => 1]);
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('cart_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('cart_revision');
            $table->uuid('creation_operation_id')->unique();
            $table->char('creation_request_hash', 64);
            $table->string('status', 24);
            $table->string('approval_status', 24);
            $table->string('payment_status', 24);
            $table->string('fulfillment_status', 24);
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamp('approval_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['cart_id', 'cart_revision']);
            $table->index(['user_id', 'created_at']);
            $table->index(['approval_status', 'approval_expires_at']);
        });
        Schema::create('order_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('policy_version_id')->constrained('commerce_policy_versions')->restrictOnDelete();
            $table->json('policy_snapshot');
            $table->json('brief');
            $table->json('billing');
            $table->string('currency', 3);
            $table->string('subtotal_minor', 30);
            $table->string('tax_minor', 30);
            $table->string('total_minor', 30);
            $table->timestamp('accepted_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['order_id', 'version']);
        });
        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_revision_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_id')->constrained('product_prices')->restrictOnDelete();
            $table->foreignId('terms_id')->constrained('product_terms')->restrictOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['order_revision_id', 'product_id']);
        });
        Schema::create('order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->char('request_hash', 64);
            $table->string('action', 40);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['order_id', 'operation_id']);
            $table->unique(['actor_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('orders')->exists() || DB::table('commerce_policy_versions')->exists()) {
            throw new RuntimeException('Retain accepted commerce records. Restore a verified backup or use a forward repair instead of destructive rollback.');
        }
        foreach (['order_events', 'order_lines', 'order_revisions', 'orders', 'commerce_settings', 'commerce_policy_versions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
