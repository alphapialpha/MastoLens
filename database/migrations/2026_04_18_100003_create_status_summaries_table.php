<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('latest_snapshot_at')->nullable();
            $table->unsignedInteger('latest_favourites_count')->default(0);
            $table->unsignedInteger('latest_boosts_count')->default(0);
            $table->unsignedInteger('latest_replies_count')->default(0);
            $table->unsignedInteger('latest_quotes_count')->default(0);
            $table->unsignedSmallInteger('snapshot_count')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('peak_total_engagement')->default(0);
            $table->unsignedInteger('engagement_after_1h')->nullable();
            $table->unsignedInteger('engagement_after_24h')->nullable();
            $table->unsignedInteger('engagement_after_7d')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_summaries');
    }
};
