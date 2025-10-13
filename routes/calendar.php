<?php

use App\Http\Controllers\Calendar\CalendarController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::post('/calendar/events', [CalendarController::class, 'store'])->name('calendar.events.store');
    Route::patch('/calendar/events/{scheduledPost}', [CalendarController::class, 'update'])->name('calendar.events.update');
    Route::delete('/calendar/events/{scheduledPost}', [CalendarController::class, 'destroy'])->name('calendar.events.destroy');
});