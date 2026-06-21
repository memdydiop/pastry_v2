<?php

use App\Http\Controllers\CinetPayWebhookController;
use App\Http\Controllers\SuperAdminLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.landing');
})->name('home');

Route::get('/super-admin/login', [SuperAdminLoginController::class, 'create'])
    ->middleware('guest')
    ->name('super-admin.login');
Route::post('/super-admin/login', [SuperAdminLoginController::class, 'store'])
    ->middleware('guest');

Route::post('/webhooks/cinetpay', [CinetPayWebhookController::class, 'handle'])
    ->name('webhooks.cinetpay');

Route::livewire('/onboarding', 'onboarding')->name('onboarding');
Route::livewire('/onboarding/success', 'onboarding-success')->name('onboarding.success');
