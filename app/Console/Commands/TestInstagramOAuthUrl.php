<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestInstagramOAuthUrl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:test-oauth-url {--open : Open the URL in browser}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and test Instagram OAuth URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔗 Generating Instagram OAuth URL...');
        $this->line('');

        // Get configuration
        $clientId = config('services.instagram.client_id');
        $redirectUri = config('services.instagram.redirect');
        
        if (!$clientId || !$redirectUri) {
            $this->error('❌ Instagram OAuth configuration missing!');
            $this->line('Please set INSTAGRAM_CLIENT_ID and INSTAGRAM_REDIRECT_URI in .env');
            return 1;
        }

        // Generate OAuth URL
        $scopes = 'user_profile,user_media';
        $state = 'test_' . time();
        
        $oauthUrl = "https://api.instagram.com/oauth/authorize?" . http_build_query([
            'app_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'response_type' => 'code',
            'state' => $state,
        ]);

        $this->info('✅ OAuth URL Generated:');
        $this->line($oauthUrl);
        $this->line('');

        // Display URL components
        $this->line('📋 URL Components:');
        $this->line("   • Endpoint: https://api.instagram.com/oauth/authorize");
        $this->line("   • App ID: {$clientId}");
        $this->line("   • Redirect URI: {$redirectUri}");
        $this->line("   • Scopes: {$scopes}");
        $this->line("   • Response Type: code");
        $this->line("   • State: {$state}");
        $this->line('');

        // Test expectations
        $this->line('🧪 Test Expectations:');
        $this->info('   ✅ SUCCESS: Instagram authorization page loads');
        $this->error('   ❌ FAILURE: "Invalid platform app" error');
        $this->line('');

        // Quick fix checklist
        $this->line('🔧 Quick Fix Checklist:');
        $this->line('   □ Instagram Basic Display product added to app');
        $this->line('   □ Redirect URI exactly matches in Facebook App settings');
        $this->line('   □ App is in Development mode with test users');
        $this->line('   □ App ID and Secret are correct');
        $this->line('');

        // Open in browser if requested
        if ($this->option('open')) {
            $this->info('🌐 Opening URL in browser...');
            $os = php_uname('s');
            
            if (str_contains($os, 'Darwin')) {
                // macOS
                exec("open '{$oauthUrl}'");
            } elseif (str_contains($os, 'Windows')) {
                // Windows
                exec("start '{$oauthUrl}'");
            } else {
                // Linux
                exec("xdg-open '{$oauthUrl}'");
            }
            
            $this->info('✅ URL opened in browser');
        } else {
            $this->line('💡 To open in browser, run: php artisan instagram:test-oauth-url --open');
        }

        $this->line('');
        $this->info('📚 For detailed fixes, see: docs/instagram-invalid-platform-app-fix.md');

        return 0;
    }
}