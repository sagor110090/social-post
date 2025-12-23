<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiagnoseInstagramOAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:diagnose-oauth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose Instagram OAuth configuration issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Diagnosing Instagram OAuth Configuration...');
        $this->line('');

        // Check environment variables
        $this->checkEnvironmentVariables();
        
        // Check OAuth URL generation
        $this->checkOAuthUrl();
        
        // Test app configuration
        $this->testAppConfiguration();
        
        // Check redirect URI
        $this->checkRedirectUri();
        
        $this->line('');
        $this->info('✅ Diagnosis complete!');
    }

    private function checkEnvironmentVariables()
    {
        $this->line('📋 Checking Environment Variables:');
        
        $clientId = config('services.instagram.client_id');
        $clientSecret = config('services.instagram.client_secret');
        $redirectUri = config('services.instagram.redirect');
        
        if ($clientId) {
            $this->info("✅ INSTAGRAM_CLIENT_ID: {$clientId}");
        } else {
            $this->error("❌ INSTAGRAM_CLIENT_ID is missing");
        }
        
        if ($clientSecret) {
            $this->info("✅ INSTAGRAM_CLIENT_SECRET: " . str_repeat('*', strlen($clientSecret) - 4) . substr($clientSecret, -4));
        } else {
            $this->error("❌ INSTAGRAM_CLIENT_SECRET is missing");
        }
        
        if ($redirectUri) {
            $this->info("✅ INSTAGRAM_REDIRECT_URI: {$redirectUri}");
        } else {
            $this->error("❌ INSTAGRAM_REDIRECT_URI is missing");
        }
        
        $this->line('');
    }

    private function checkOAuthUrl()
    {
        $this->line('🔗 Checking OAuth URL Generation:');
        
        $clientId = config('services.instagram.client_id');
        $redirectUri = config('services.instagram.redirect');
        $scopes = 'user_profile,user_media';
        $state = 'test_state_123';
        
        if (!$clientId || !$redirectUri) {
            $this->error("❌ Cannot generate OAuth URL - missing credentials");
            $this->line('');
            return;
        }
        
        $oauthUrl = "https://api.instagram.com/oauth/authorize?" . http_build_query([
            'app_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'response_type' => 'code',
            'state' => $state,
        ]);
        
        $this->info("✅ OAuth URL generated successfully:");
        $this->line($oauthUrl);
        
        // Validate URL components
        $this->line('');
        $this->line('URL Components:');
        $this->info("- Endpoint: https://api.instagram.com/oauth/authorize");
        $this->info("- App ID: {$clientId}");
        $this->info("- Redirect URI: {$redirectUri}");
        $this->info("- Scopes: {$scopes}");
        $this->info("- Response Type: code");
        $this->info("- State: {$state}");
        
        $this->line('');
    }

    private function testAppConfiguration()
    {
        $this->line('🧪 Testing App Configuration:');
        
        $clientId = config('services.instagram.client_id');
        
        if (!$clientId) {
            $this->error("❌ Cannot test app - missing client ID");
            $this->line('');
            return;
        }
        
        // Test app discovery endpoint
        try {
            $response = Http::get("https://graph.facebook.com/{$clientId}", [
                'fields' => 'id,name,category'
            ]);
            
            if ($response->successful()) {
                $appData = $response->json();
                $this->info("✅ App found on Facebook:");
                $this->info("- App ID: {$appData['id']}");
                $this->info("- Name: {$appData['name']}");
                $this->info("- Category: {$appData['category']}");
            } else {
                $this->error("❌ App not found or not accessible:");
                $this->error($response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ Error testing app configuration:");
            $this->error($e->getMessage());
        }
        
        $this->line('');
    }

    private function checkRedirectUri()
    {
        $this->line('🔄 Checking Redirect URI:');
        
        $redirectUri = config('services.instagram.redirect');
        
        if (!$redirectUri) {
            $this->error("❌ No redirect URI configured");
            $this->line('');
            return;
        }
        
        // Validate redirect URI format
        $this->info("✅ Redirect URI: {$redirectUri}");
        
        // Check if it's HTTPS
        if (str_starts_with($redirectUri, 'https://')) {
            $this->info("✅ Using HTTPS (required)");
        } else {
            $this->error("❌ Not using HTTPS (required for production)");
        }
        
        // Check for trailing slash
        if (str_ends_with($redirectUri, '/')) {
            $this->warning("⚠️  Ends with trailing slash - ensure exact match in Facebook App settings");
        }
        
        // Check if it matches expected format
        $expectedPattern = '/oauth/instagram/callback';
        if (str_contains($redirectUri, $expectedPattern)) {
            $this->info("✅ Contains expected callback path: {$expectedPattern}");
        } else {
            $this->error("❌ Does not contain expected callback path: {$expectedPattern}");
        }
        
        // Test if redirect URI is accessible
        try {
            $response = Http::get($redirectUri);
            $this->info("✅ Redirect URI is accessible (HTTP {$response->status()})");
        } catch (\Exception $e) {
            $this->warning("⚠️  Cannot test redirect URI accessibility:");
            $this->warning($e->getMessage());
        }
        
        $this->line('');
        $this->line('📝 Manual Check Required:');
        $this->line('1. Go to: https://developers.facebook.com/apps/' . config('services.instagram.client_id') . '/instagram-basic-display/');
        $this->line('2. Verify this redirect URI is added exactly:');
        $this->line("   {$redirectUri}");
        $this->line('3. Ensure Instagram Basic Display product is added to your app');
        $this->line('4. Check app is in Development mode (for testing)');
    }
}