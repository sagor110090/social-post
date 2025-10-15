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
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('username');
            $table->string('email')->nullable()->after('display_name');
            $table->string('avatar')->nullable()->after('email');
            $table->json('additional_data')->nullable()->after('refresh_token');
            $table->timestamp('last_synced_at')->nullable()->after('token_expires_at');
            
            // Add indexes for better performance
            $table->index(['user_id', 'platform']);
            $table->index('platform_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'email', 'avatar', 'additional_data', 'last_synced_at']);
            
            // Drop indexes
            $table->dropIndex(['user_id', 'platform']);
            $table->dropIndex('platform_id');
        });
    }
};
