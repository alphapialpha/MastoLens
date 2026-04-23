<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_account_id')->constrained()->cascadeOnDelete();
            $table->string('instance_domain');
            $table->string('remote_status_id');
            $table->string('status_url')->nullable();
            $table->string('uri')->nullable();
            $table->timestamp('created_at_remote')->nullable();
            $table->timestamp('edited_at_remote')->nullable();
            $table->timestamp('fetched_first_at')->nullable();
            $table->timestamp('fetched_last_at')->nullable();
            $table->text('content_html')->nullable();
            $table->text('content_text')->nullable();
            $table->string('spoiler_text')->nullable();
            $table->string('language', 10)->nullable();
            $table->string('visibility', 20)->default('public');
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_reply')->default(false);
            $table->boolean('is_boost')->default(false);
            $table->boolean('has_media')->default(false);
            $table->boolean('has_poll')->default(false);
            $table->boolean('has_card')->default(false);
            $table->string('in_reply_to_remote_status_id')->nullable();
            $table->string('in_reply_to_remote_account_id')->nullable();
            $table->string('boost_of_remote_status_id')->nullable();
            $table->unsignedSmallInteger('media_count')->default(0);
            $table->json('mentions_json')->nullable();
            $table->json('tags_json')->nullable();
            $table->json('emojis_json')->nullable();
            $table->json('poll_json')->nullable();
            $table->json('card_json')->nullable();
            $table->json('raw_payload_json')->nullable();
            $table->string('tracking_state', 20)->default('discovered');
            $table->timestamp('next_snapshot_due_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['tracked_account_id', 'remote_status_id']);
            $table->index(['tracked_account_id', 'created_at_remote']);
            $table->index(['tracked_account_id', 'tracking_state']);
            $table->index('next_snapshot_due_at');
            $table->index('is_reply');
            $table->index('is_boost');
            $table->index('has_media');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
