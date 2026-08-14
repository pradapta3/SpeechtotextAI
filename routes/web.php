<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MinutesController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TranscriptionChunkController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

/*
 * Endpoint berikut dipanggil dari browser lewat fetch(). Semuanya tetap berada
 * di grup "web" supaya session (kepemilikan rekaman + API key pengguna) dan
 * proteksi CSRF ikut berlaku.
 */
Route::prefix('api')->name('api.')->group(function (): void {
    Route::get('recordings', [RecordingController::class, 'index'])->name('recordings.index');
    Route::post('recordings', [RecordingController::class, 'store'])->name('recordings.store');
    Route::patch('recordings/{recording}', [RecordingController::class, 'update'])->name('recordings.update');
    Route::delete('recordings/{recording}', [RecordingController::class, 'destroy'])->name('recordings.destroy');

    Route::post('recordings/{recording}/chunks', [TranscriptionChunkController::class, 'store'])
        ->middleware('throttle:transcription')
        ->name('recordings.chunks.store');

    Route::post('recordings/{recording}/minutes', [MinutesController::class, 'store'])
        ->middleware('throttle:minutes')
        ->name('recordings.minutes.store');

    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});
