<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\LocaleController;
use App\Livewire\Admin\UserManager;
use Illuminate\Support\Facades\Route;

Route::post('/locale', LocaleController::class)->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [MagicLinkController::class, 'create'])->name('login');
    Route::post('/login', [MagicLinkController::class, 'store'])
        ->middleware('throttle:magic-link')
        ->name('login.send');
    Route::get('/login/{token}', [MagicLinkController::class, 'show'])->name('login.confirm');
    Route::post('/login/{token}', [MagicLinkController::class, 'consume'])
        ->middleware('throttle:magic-link-consume')
        ->name('login.consume');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout', [MagicLinkController::class, 'destroy'])->name('logout');

    Route::get('/admin/users', UserManager::class)
        ->middleware('can:manage-users')
        ->name('admin.users');
});
