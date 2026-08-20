<?php

declare(strict_types=1);

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DemoLoginController;
use Illuminate\Support\Facades\Route;

// Named 'login' so the auth middleware sends guests here.
Route::view('/', 'landing')->name('login');

Route::post('/demo-login', DemoLoginController::class)->name('demo-login');

Route::middleware('auth')->group(function (): void {
    Route::get('/chat', [ChatController::class, 'show'])->name('chat');
    Route::post('/chat', [ChatController::class, 'store'])->name('chat.store');
});
