<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->text('summary');
            $table->string('status', 40)->default('not_started');
            $table->text('next_step')->nullable();
            $table->date('target_date')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('customer_read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type', 'status']);
        });
        Schema::create('work_milestones', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('work_item_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->date('target_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['work_item_id', 'id']);
        });
        Schema::create('work_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at');
            $table->index(['work_item_id', 'id']);
        });
        Schema::create('support_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('support_cases', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('support_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('subject');
            $table->text('description');
            $table->string('status', 40)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('customer_read_at')->nullable();
            $table->timestamp('staff_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('public_updated_at');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        foreach (['support_messages', 'support_notes'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_case_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->text('body');
                $table->timestamp('created_at');
                $table->index(['support_case_id', 'id']);
            });
        }
        Schema::create('portal_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('support_case_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('support_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action');
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['work_item_id', 'id']);
            $table->index(['support_case_id', 'id']);
        });
        Schema::create('portal_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->string('fingerprint', 64);
            $table->string('result_type');
            $table->unsignedBigInteger('result_id');
            $table->timestamp('created_at');
            $table->unique(['actor_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        foreach (['work_items', 'support_cases', 'portal_commands'] as $name) {
            if (Schema::hasTable($name) && DB::table($name)->exists()) {
                throw new RuntimeException('Retain portal history: restore a backup instead of reversing populated portal tables.');
            }
        }
        foreach (['portal_commands', 'portal_events', 'support_notes', 'support_messages', 'support_cases', 'support_categories', 'work_updates', 'work_milestones', 'work_items'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
