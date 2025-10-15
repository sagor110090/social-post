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
        Schema::create('webhook_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->onDelete('cascade');
            $table->string('webhook_url'); // The URL where webhooks are sent
            $table->string('secret')->nullable(); // Webhook secret for signature verification
            $table->json('events'); // Array of subscribed events
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional platform-specific config
            $table->timestamp('last_verified_at')->nullable(); // When webhook was last verified
            $table->timestamps();

            // Indexes
            $table->index(['social_account_id', 'is_active']);
            $table->unique(['social_account_id', 'webhook_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_configs');
    }
};