<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Do not silently choose between pre-existing owned carts. Resolve any
        // ambiguous data before this migration changes the schema.
        if (DB::table('carts')->select('user_id')->whereNotNull('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Resolve multiple existing customer carts before enabling active-cart ownership.');
        }
        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignId('merged_into_cart_id')->nullable()->constrained('carts')->restrictOnDelete();
        });
        Schema::create('customer_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('cart_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        DB::table('carts')->whereNotNull('user_id')->orderBy('id')->each(function ($cart): void {
            DB::table('customer_carts')->insert(['user_id' => $cart->user_id, 'cart_id' => $cart->id, 'created_at' => now(), 'updated_at' => now()]);
        });
        // Reapply after reversal reconstructs retired-source links from the
        // retained immutable ledger. Rotated guest credentials remain revoked.
        DB::table('cart_changes')->where('action', 'merge')->whereNotNull('actor_id')->orderBy('id')->each(function ($change): void {
            $source = DB::table('carts')->where('id', $change->cart_id)->whereNull('user_id')->first();
            if ($source === null) {
                return;
            }
            $effects = json_decode($change->effects, true, flags: JSON_THROW_ON_ERROR);
            $target = DB::table('carts')->where('public_id', $effects['target'] ?? '')->where('user_id', $change->actor_id)->whereNull('credential_hash')->first();
            if ($target === null || $source->id === $target->id) {
                throw new RuntimeException('Resolve inconsistent retained cart merge history before reapplying ownership.');
            }
            DB::table('carts')->where('id', $source->id)->update(['merged_into_cart_id' => $target->id]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_carts');
        Schema::table('carts', fn (Blueprint $table) => $table->dropConstrainedForeignId('merged_into_cart_id'));
    }
};
