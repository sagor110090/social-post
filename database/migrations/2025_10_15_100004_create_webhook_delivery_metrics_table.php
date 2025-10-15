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
        Schema::create('webhook_delivery_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_config_id')->constrained()->onDelete('cascade');
            $table->foreignId('social_account_id')->constrained()->onDelete('cascade');
            $table->string('platform');
            $table->date('date');
            $table->integer('total_received')->default(0);
            $table->integer('successfully_processed')->default(0);
            $table->integer('failed')->default(0);
            $table->integer('ignored')->default(0);
            $table->integer('retry_attempts')->default(0);
            $table->decimal('average_processing_time', 8, 3)->default(0); // in seconds
            $table->json('event_type_breakdown')->nullable(); // Count by event type
            $table->timestamps();

            // Indexes
            $table->unique(['webhook_config_id', 'date']);
            $table->index(['social_account_id', 'platform', 'date']);
            $table->index(['date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_metrics');
    }
};