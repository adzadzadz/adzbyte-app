<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_proposals', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('policy_version_id')->constrained('commerce_policy_versions')->restrictOnDelete();
            $table->unsignedBigInteger('base_revision');
            $table->json('content');
            $table->char('content_hash', 64);
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('pending_proposal_id')->nullable()->constrained('order_proposals')->restrictOnDelete();
        });
        Schema::create('order_messages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->unique()->constrained('order_events')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('audience', 16);
            $table->string('sender', 16);
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'audience', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('order_proposals')->exists() || DB::table('order_messages')->exists()
            || DB::table('order_events')->whereNotIn('action', ['submitted', 'submission_replayed'])->exists()) {
            throw new RuntimeException('Retain review history. Use a verified backup restore or forward repair.');
        }
        Schema::dropIfExists('order_messages');
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('pending_proposal_id'));
        Schema::dropIfExists('order_proposals');
    }
};
