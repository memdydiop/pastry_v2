<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {
    // Personal profile routes (auth required)
    Route::middleware(['auth'])->group(function () {
        Route::redirect('settings', 'settings/profile');
        Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    });

    // System configuration routes (auth + permission required)
    Route::middleware(['auth', 'permission:manage-settings'])->group(function () {
        Route::livewire('settings/system', 'pages::settings.system')->name('settings.index');
        Route::livewire('settings/users', 'pages::settings.users')->name('settings.users');
        Route::livewire('settings/users/{user}', 'pages::users.show')->name('users.show');
    });
});
