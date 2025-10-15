<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('webhook_config_id')->nullable()->constrained()->onDelete('set null');
            $table->string('platform'); // facebook, instagram, twitter, linkedin
            $table->string('event_type'); // e.g., 'page_post', 'media_comment', 'tweet_mention'
            $table->string('event_id')->nullable(); // Platform-specific event ID
            $table->string('object_type')->nullable(); // e.g., 'page', 'user', 'post'
            $table->string('object_id')->nullable(); // Platform-specific object ID
            $table->json('payload'); // Full webhook payload
            $table->string('signature')->nullable(); // Webhook signature for verification
            $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'ignored'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['social_account_id', 'platform', 'status']);
            $table->index(['status', 'received_at']);
            $table->index(['platform', 'event_type']);
            $table->index(['object_type', 'object_id']);
            $table->unique(['platform', 'event_id']); // Prevent duplicate events
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
