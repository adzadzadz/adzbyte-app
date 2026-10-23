<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_deletions', function (Blueprint $table): void {
            $table->id();
            $table->string('record_type', 80);
            $table->string('record_id', 64);
            $table->timestamp('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['record_type', 'record_id']);
        });
        Schema::create('record_deletion_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_deletion_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 16);
            $table->unsignedBigInteger('version');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['record_deletion_id', 'version']);
        });
        DB::table('permissions')->insertOrIgnore(['name' => 'Manage:DataRecords', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('record_deletion_events')->exists()) {
            throw new RuntimeException('Deletion history must be retained. Use a forward repair or verified backup.');
        }
        Schema::dropIfExists('record_deletion_events');
        Schema::dropIfExists('record_deletions');
    }
};
