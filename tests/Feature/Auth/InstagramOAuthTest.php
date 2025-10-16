<?php

use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Mockery;

beforeEach(function () {
    $this->user = \App\Models\User::factory()->create();
    $this->actingAs($this->user);
});

test('redirects to instagram oauth', function () {
    $response = $this->get('/oauth/instagram');
    
    $response->assertRedirect();
    $this->assertStringContainsString('facebook.com', $response->getTargetUrl());
});

test('handles instagram oauth callback', function () {
    // Mock the Socialite facade properly
    $mockProvider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $mockUser = Mockery::mock('Laravel\Socialite\Contracts\User');
    
    $mockUser->shouldReceive('getId')->andReturn('123456789');
    $mockUser->shouldReceive('getEmail')->andReturn('user@example.com');
    $mockUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
    $mockUser->shouldReceive('getToken')->andReturn('mock_access_token');
    $mockUser->shouldReceive('getRefreshToken')->andReturn(null);
    $mockUser->shouldReceive('getExpiresIn')->andReturn(3600);
    $mockUser->shouldReceive('getTokenType')->andReturn('Bearer');
    
    // Add the token property
    $mockUser->token = 'mock_access_token';
    $mockUser->refreshToken = null;
    $mockUser->expiresIn = 3600;
    $mockUser->tokenType = 'Bearer';
    
    $mockProvider->shouldReceive('user')->andReturn($mockUser);
    
    // Mock the Instagram API call
    Http::fake([
        'graph.instagram.com/me*' => Http::response([
            'id' => '123456789',
            'username' => 'testuser',
            'account_type' => 'BUSINESS',
            'media_count' => 42
        ], 200),
        
        'graph.instagram.com/access_token*' => Http::response([
            'access_token' => 'long_lived_token',
            'expires_in' => 5184000 // 60 days
        ], 200)
    ]);
    
    // Mock Socialite facade
    Socialite::shouldReceive('driver')->with('facebook')->andReturn($mockProvider);
    
    $response = $this->get('/oauth/instagram/callback?code=test_code');
    
    // Check for session errors
    if ($response->getSession()->has('error')) {
        dump('Error in session:', $response->getSession()->get('error'));
    }
    
    $response->assertRedirect('/dashboard');
    
    // Check that the social account was created
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $this->user->id,
        'platform' => 'instagram',
        'platform_id' => '123456789',
        'username' => 'testuser',
    ]);
});

test('fails instagram oauth with invalid code', function () {
    // Mock Socialite to throw an exception
    Socialite::shouldReceive('driver')
        ->with('facebook')
        ->andThrow(new \Exception('Invalid OAuth code'));
    
    $response = $this->get('/oauth/instagram/callback?error=access_denied');
    
    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('error');
});