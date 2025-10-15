<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookMonitoringController;
use App\Http\Controllers\WebhookLogsController;

/*
|--------------------------------------------------------------------------
| Monitoring Routes
|--------------------------------------------------------------------------
|
| Routes for webhook monitoring, metrics, health checks, and logging.
|
*/

Route::prefix('monitoring')->name('monitoring.')->group(function () {
    
    // Dashboard and overview
    Route::get('/dashboard', [WebhookMonitoringController::class, 'dashboard'])->name('dashboard');
    Route::get('/test', [WebhookMonitoringController::class, 'test'])->name('test');
    Route::get('/config', [WebhookMonitoringController::class, 'config'])->name('config');
    
    // Health checks
    Route::get('/health', [WebhookMonitoringController::class, 'health'])->name('health');
    Route::get('/health/{check}', [WebhookMonitoringController::class, 'health'])->name('health.check');
    
    // Metrics
    Route::get('/metrics', [WebhookMonitoringController::class, 'metrics'])->name('metrics');
    Route::get('/metrics/system', [WebhookMonitoringController::class, 'systemMetrics'])->name('metrics.system');
    
    // Alerts
    Route::prefix('alerts')->name('alerts.')->group(function () {
        Route::get('/', [WebhookMonitoringController::class, 'alerts'])->name('list');
        Route::post('/evaluate', [WebhookMonitoringController::class, 'alerts'])->name('evaluate');
        Route::post('/trigger', [WebhookMonitoringController::class, 'alerts'])->name('trigger');
        Route::post('/suppress', [WebhookMonitoringController::class, 'alerts'])->name('suppress');
        Route::delete('/suppress', [WebhookMonitoringController::class, 'alerts'])->name('suppress.clear');
    });
    
    // Logs
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [WebhookLogsController::class, 'index'])->name('index');
        Route::get('/search', [WebhookLogsController::class, 'search'])->name('search');
        Route::get('/stats', [WebhookLogsController::class, 'stats'])->name('stats');
        Route::get('/channels', [WebhookLogsController::class, 'channels'])->name('channels');
        Route::get('/download', [WebhookLogsController::class, 'download'])->name('download');
        Route::delete('/', [WebhookLogsController::class, 'clear'])->name('clear');
    });
});

// API monitoring routes (for external monitoring services)
Route::prefix('api/monitoring')->name('api.monitoring.')->group(function () {
    Route::get('/health', [WebhookMonitoringController::class, 'health']);
    Route::get('/metrics', [WebhookMonitoringController::class, 'metrics']);
    Route::get('/status', [WebhookMonitoringController::class, 'test']);
});