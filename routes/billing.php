<?php

use App\Http\Controllers\Billing\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('billing.subscription');
    Route::post('/subscription', [SubscriptionController::class, 'subscribe'])->name('billing.subscribe');
    Route::patch('/subscription', [SubscriptionController::class, 'swap'])->name('billing.swap');
    Route::delete('/subscription', [SubscriptionController::class, 'cancel'])->name('billing.cancel');
    Route::post('/subscription/resume', [SubscriptionController::class, 'resume'])->name('billing.resume');
    Route::patch('/payment-method', [SubscriptionController::class, 'updatePaymentMethod'])->name('billing.payment-method.update');
    Route::get('/invoices', [SubscriptionController::class, 'invoices'])->name('billing.invoices');
});