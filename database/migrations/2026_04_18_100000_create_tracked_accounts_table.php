<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('acct_input');
            $table->string('username');
            $table->string('instance_domain');
            $table->string('acct_normalized');
            $table->string('remote_account_id')->nullable();
            $table->string('account_url')->nullable();
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('note_html')->nullable();
            $table->unsignedInteger('followers_count_latest')->nullable();
            $table->unsignedInteger('following_count_latest')->nullable();
            $table->unsignedInteger('statuses_count_latest')->nullable();
            $table->timestamp('last_status_at_remote')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_resolved_at')->nullable();
            $table->timestamp('last_sync_started_at')->nullable();
            $table->timestamp('last_sync_finished_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'acct_normalized']);
            $table->index('is_active');
            $table->index('instance_domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_accounts');
    }
};
