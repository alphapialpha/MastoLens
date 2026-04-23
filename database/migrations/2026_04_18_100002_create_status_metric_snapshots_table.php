<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedInteger('snapshot_target_age_minutes');
            $table->unsignedInteger('actual_age_minutes');
            $table->unsignedInteger('favourites_count')->default(0);
            $table->unsignedInteger('boosts_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('quotes_count')->default(0);
            $table->json('raw_payload_json')->nullable();
            $table->timestamps();

            $table->unique(['status_id', 'snapshot_target_age_minutes'], 'snapshots_status_target_age_unique');
            $table->index(['status_id', 'captured_at'], 'snapshots_status_captured_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_metric_snapshots');
    }
};
