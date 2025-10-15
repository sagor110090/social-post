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
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_config_id')->constrained()->onDelete('cascade');
            $table->string('platform'); // facebook, instagram, twitter, linkedin
            $table->string('event_type'); // e.g., 'page_posts', 'media_comments', 'tweet_events'
            $table->string('subscription_id')->nullable(); // Platform-specific subscription ID
            $table->enum('status', ['active', 'inactive', 'expired', 'failed'])->default('active');
            $table->timestamp('subscribed_at');
            $table->timestamp('expires_at')->nullable();
            $table->json('subscription_data')->nullable(); // Platform-specific subscription details
            $table->timestamps();

            // Indexes
            $table->index(['webhook_config_id', 'platform', 'status']);
            $table->unique(['webhook_config_id', 'platform', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};