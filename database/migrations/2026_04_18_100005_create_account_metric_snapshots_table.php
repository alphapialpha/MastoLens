<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('followers_count');
            $table->unsignedInteger('following_count');
            $table->unsignedInteger('statuses_count');
            $table->timestamp('captured_at');
            $table->date('snapshot_date');
            $table->timestamps();

            $table->unique(['tracked_account_id', 'snapshot_date']);
            $table->index(['tracked_account_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_metric_snapshots');
    }
};
