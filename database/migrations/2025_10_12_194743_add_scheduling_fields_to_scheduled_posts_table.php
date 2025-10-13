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
        Schema::table('scheduled_posts', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('scheduled_posts', 'platforms')) {
                $table->json('platforms')->nullable();
            }
            
            if (!Schema::hasColumn('scheduled_posts', 'results')) {
                $table->json('results')->nullable();
            }
            
            if (!Schema::hasColumn('scheduled_posts', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            
            if (!Schema::hasColumn('scheduled_posts', 'failed_at')) {
                $table->timestamp('failed_at')->nullable();
            }
            
            // Rename column if it exists
            if (Schema::hasColumn('scheduled_posts', 'scheduled_for') && !Schema::hasColumn('scheduled_posts', 'scheduled_at')) {
                $table->renameColumn('scheduled_for', 'scheduled_at');
            }
            
            // Note: Indexes may already exist, so we'll skip adding them in this migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_posts', function (Blueprint $table) {
            // Drop columns if they exist
            if (Schema::hasColumn('scheduled_posts', 'platforms')) {
                $table->dropColumn('platforms');
            }
            
            if (Schema::hasColumn('scheduled_posts', 'results')) {
                $table->dropColumn('results');
            }
            
            if (Schema::hasColumn('scheduled_posts', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
            
            if (Schema::hasColumn('scheduled_posts', 'failed_at')) {
                $table->dropColumn('failed_at');
            }
            
            // Rename column back if needed
            if (Schema::hasColumn('scheduled_posts', 'scheduled_at') && !Schema::hasColumn('scheduled_posts', 'scheduled_for')) {
                $table->renameColumn('scheduled_at', 'scheduled_for');
            }
            
            // Note: Indexes are not dropped in this migration
        });
    }
};
