<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_checkouts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->uuid('operation_id')->unique();
            $table->char('request_hash', 64);
            $table->char('proof_hash', 64);
            $table->string('package_code', 32);
            $table->string('package_name', 120);
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedTinyInteger('quantity');
            $table->string('offer_version', 32);
            $table->text('business_name');
            $table->text('email');
            $table->string('provider_mode', 8);
            $table->string('status', 16)->index();
            $table->string('creation_phase', 32);
            $table->string('provider_intent_id', 100)->nullable()->unique();
            $table->string('provider_method_id', 100)->nullable()->unique();
            $table->string('provider_payment_id', 100)->nullable()->unique();
            $table->mediumText('qr_image')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('package_qrph_events', function (Blueprint $table): void {
            $table->string('id', 100)->primary();
            $table->char('payload_hash', 64);
            $table->string('event_type', 100);
            $table->string('outcome', 32);
            $table->foreignUlid('package_checkout_id')->nullable()->constrained('package_checkouts')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_qrph_events');
        Schema::dropIfExists('package_checkouts');
    }
};
