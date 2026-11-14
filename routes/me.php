<?php

use App\Http\Controllers\Me\LibraryController;
use App\Http\Controllers\Me\OperateController;
use App\Http\Controllers\Me\PreferenceController;
use App\Http\Controllers\Me\SavedController;
use App\Http\Controllers\Me\WatchController;
use App\Http\Middleware\EnsureCompanyEntitled;
use App\Http\Middleware\ReverifyWithInfoDot;
use Illuminate\Support\Facades\Route;

// One click from an email turns the digest off. The link is signed, so it needs no sign-in and cannot be guessed.
Route::get('/me/digest/stop/{user}', [PreferenceController::class, 'stop'])->middleware(['signed', 'throttle:20,1'])->name('me.digest.stop');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', ReverifyWithInfoDot::class, EnsureCompanyEntitled::class])->prefix('me')->group(function () {
    Route::get('/', [LibraryController::class, 'home'])->name('me.home');
    Route::get('/watchlist', [LibraryController::class, 'watchlist'])->name('me.watchlist');
    Route::get('/saved', [LibraryController::class, 'saved'])->name('me.saved');
    Route::get('/alerts', [LibraryController::class, 'alerts'])->name('me.alerts');
    Route::post('/alerts/read', [LibraryController::class, 'readAlerts'])->name('me.alerts.read');

    // Reached from a public page by a plain link, so the public page never needs a session. Nothing changes on the GET.
    Route::get('/watch', [WatchController::class, 'confirm'])->name('me.watch.confirm');
    Route::post('/watch', [WatchController::class, 'store'])->name('me.watch.store');
    Route::delete('/watch/{watch}', [WatchController::class, 'destroy'])->name('me.watch.destroy');

    Route::get('/save', [SavedController::class, 'confirm'])->name('me.save.confirm');
    Route::post('/save', [SavedController::class, 'store'])->name('me.save.store');
    Route::delete('/saved/{saved}', [SavedController::class, 'destroy'])->name('me.saved.destroy');

    Route::get('/preferences', [PreferenceController::class, 'show'])->name('me.preferences');
    Route::put('/preferences', [PreferenceController::class, 'update'])->name('me.preferences.update');
    Route::get('/export', [PreferenceController::class, 'export'])->name('me.export');
    Route::get('/operate', OperateController::class)->name('me.operate');
    Route::delete('/data', [PreferenceController::class, 'erase'])->name('me.erase');
});
