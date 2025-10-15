<?php


use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HasSocialAccount;
use App\Http\Middleware\RequestLoggingMiddleware;
use App\Http\Middleware\PerformanceMonitoringMiddleware;
use App\Http\Middleware\Webhooks\VerifyWebhookSignature;
use App\Http\Middleware\Webhooks\WebhookRateLimiting;
use App\Http\Middleware\Webhooks\WhitelistWebhookIps;
use App\Http\Middleware\Webhooks\ValidateWebhookRequest;
use App\Http\Middleware\Webhooks\WebhookSecurityHeaders;
use App\Http\Middleware\Webhooks\LogWebhookActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
          'api/*',
      ]);

        // Register webhook security middleware groups
        $middleware->group('webhook-security', [
            LogWebhookActivity::class,
            ValidateWebhookRequest::class,
            WhitelistWebhookIps::class,
            WebhookRateLimiting::class,
            VerifyWebhookSignature::class,
            WebhookSecurityHeaders::class,
        ]);

        // Register webhook management middleware group
        $middleware->group('webhook-management', [
            LogWebhookActivity::class,
            ValidateWebhookRequest::class,
            WebhookRateLimiting::class,
            WebhookSecurityHeaders::class,
        ]);

        // Register monitoring middleware groups
        $middleware->group('monitoring', [
            RequestLoggingMiddleware::class,
            PerformanceMonitoringMiddleware::class,
        ]);

        // Alias for individual middleware
        $middleware->alias([
            'webhook.signature' => VerifyWebhookSignature::class,
            'webhook.ratelimit' => WebhookRateLimiting::class,
            'webhook.ipwhitelist' => WhitelistWebhookIps::class,
            'webhook.validate' => ValidateWebhookRequest::class,
            'webhook.headers' => WebhookSecurityHeaders::class,
            'webhook.logging' => LogWebhookActivity::class,
            'monitoring.request' => RequestLoggingMiddleware::class,
            'monitoring.performance' => PerformanceMonitoringMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })

    ->create();
