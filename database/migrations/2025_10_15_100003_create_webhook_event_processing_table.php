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
        Schema::create('webhook_event_processing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('scheduled_post_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('post_analytics_id')->nullable()->constrained()->onDelete('set null');
            $table->string('processor_type'); // e.g., 'analytics_updater', 'post_sync', 'notification'
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('processing_data')->nullable(); // Data extracted for processing
            $table->json('result')->nullable(); // Processing result
            $table->text('error_message')->nullable();
            $table->integer('attempt')->default(1);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['webhook_event_id', 'status']);
            $table->index(['status', 'next_attempt_at']);
            $table->index(['processor_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_event_processing');
    }
};