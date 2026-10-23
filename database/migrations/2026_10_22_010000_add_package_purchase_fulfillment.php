<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_checkouts', function (Blueprint $table): void {
            $table->text('full_name')->nullable();
            $table->text('phone')->nullable();
        });
        Schema::create('package_purchase_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('package_checkout_id')->constrained('package_checkouts')->restrictOnDelete();
            $table->string('phase', 16);
            $table->text('payload');
            $table->char('payload_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->string('last_error_code', 40)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
            $table->unique(['package_checkout_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_purchase_deliveries');
        Schema::table('package_checkouts', function (Blueprint $table): void {
            $table->dropColumn(['full_name', 'phone']);
        });
    }
};
