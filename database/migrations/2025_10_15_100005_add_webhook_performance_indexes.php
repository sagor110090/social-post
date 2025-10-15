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
        Schema::table('webhook_events', function (Blueprint $table) {
            // Composite indexes for common queries
            $table->index(['status', 'platform', 'received_at']);
            $table->index(['social_account_id', 'event_type', 'status']);
            $table->index(['object_type', 'object_id', 'platform']);
        });

        Schema::table('webhook_event_processing', function (Blueprint $table) {
            // Composite indexes for processing queue
            $table->index(['status', 'next_attempt_at', 'processor_type']);
            $table->index(['webhook_event_id', 'status', 'attempt']);
        });

        Schema::table('webhook_delivery_metrics', function (Blueprint $table) {
            // Composite indexes for analytics queries
            $table->index(['platform', 'date', 'total_received']);
            $table->index(['social_account_id', 'date', 'successfully_processed']);
        });

        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            // Index for subscription management
            $table->index(['platform', 'event_type', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'platform', 'received_at']);
            $table->dropIndex(['social_account_id', 'event_type', 'status']);
            $table->dropIndex(['object_type', 'object_id', 'platform']);
        });

        Schema::table('webhook_event_processing', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_attempt_at', 'processor_type']);
            $table->dropIndex(['webhook_event_id', 'status', 'attempt']);
        });

        Schema::table('webhook_delivery_metrics', function (Blueprint $table) {
            $table->dropIndex(['platform', 'date', 'total_received']);
            $table->dropIndex(['social_account_id', 'date', 'successfully_processed']);
        });

        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['platform', 'event_type', 'status']);
            $table->dropIndex(['expires_at', 'status']);
        });
    }
};