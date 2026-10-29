<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventPublicationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManageSignupController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\SignupController;
use App\Livewire\Admin\UserManager;
use App\Livewire\Events\EventEditor;
use App\Livewire\Events\FormBuilder;
use App\Livewire\Events\SignupList;
use Illuminate\Support\Facades\Route;

Route::post('/locale', LocaleController::class)->name('locale.update');

Route::get('/e/{event:slug}', PublicEventController::class)->name('events.public');
Route::post('/e/{event:slug}/signups', [SignupController::class, 'store'])
    ->middleware('throttle:signups')
    ->name('signups.store');

Route::middleware('throttle:manage-signup')->group(function () {
    Route::get('/s/{token}', [ManageSignupController::class, 'show'])->name('signups.show');
    Route::put('/s/{token}', [ManageSignupController::class, 'update'])->name('signups.update');
    Route::delete('/s/{token}', [ManageSignupController::class, 'destroy'])->name('signups.destroy');
});

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
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [MagicLinkController::class, 'destroy'])->name('logout');

    Route::get('/events/create', EventEditor::class)->name('events.create');
    Route::get('/events/{event}/edit', EventEditor::class)->name('events.edit');
    Route::get('/events/{event}/form', FormBuilder::class)->name('events.form');
    Route::get('/events/{event}/signups', SignupList::class)->name('events.signups');
    Route::post('/events/{event}/publish', [EventPublicationController::class, 'publish'])->name('events.publish');
    Route::post('/events/{event}/close', [EventPublicationController::class, 'close'])->name('events.close');
    Route::post('/events/{event}/reopen', [EventPublicationController::class, 'reopen'])->name('events.reopen');

    Route::get('/admin/users', UserManager::class)
        ->middleware('can:manage-users')
        ->name('admin.users');
});
