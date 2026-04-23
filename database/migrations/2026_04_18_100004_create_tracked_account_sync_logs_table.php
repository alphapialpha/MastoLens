<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_account_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_account_id')->constrained()->cascadeOnDelete();
            $table->string('job_type', 50);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('posts_examined')->default(0);
            $table->unsignedInteger('new_posts_count')->default(0);
            $table->unsignedInteger('updated_posts_count')->default(0);
            $table->unsignedInteger('snapshots_created_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['tracked_account_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_account_sync_logs');
    }
};
