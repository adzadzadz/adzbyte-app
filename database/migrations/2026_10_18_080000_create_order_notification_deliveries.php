<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('order_events')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('audience', 16);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('suppressed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['event_id', 'user_id']);
            $table->index(['sent_at', 'failed_at', 'queued_at'], 'order_notification_recovery');
        });
    }

    public function down(): void
    {
        if (DB::table('order_notification_deliveries')->exists()) {
            throw new RuntimeException('Retain notification delivery history. Use verified backup restore or forward repair.');
        }
        Schema::dropIfExists('order_notification_deliveries');
    }
};
