<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::view('/welcome', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::livewire('/setup-password', 'pages::auth.setup-password')->name('setup-password');
});

// Le groupe principal intègre 'auth' et l'activité globale est gérée par app.php
Route::middleware(['auth'])->prefix('admin')->group(function () {

    // Tableau de bord général accessible par tout le personnel connecté
    Route::livewire('/', 'pages::dashboard')->name('dashboard');

    // MODULE 2 : Commandes & Clients
    // Autorise : Gérant, le Chef, le Caissier (ventes directes) et le Pâtissier (suivi de fabrication)
    Route::middleware(['role:Gérant/Admin|Chef Pâtissier|Pâtissier|Caissier|ghost'])->group(function () {
        Route::livewire('/orders', 'pages::orders.index')->name('orders.index');
        Route::livewire('/orders/{order}', 'pages::orders.show')->name('orders.show');
        Route::livewire('/clients', 'pages::clients.index')->name('clients.index');
    });

    // MODULE 3 & 6 : Production, Recettes & Stock
    // Réservé à l'encadrement et à la technique du laboratoire
    Route::middleware(['role:Gérant/Admin|Chef Pâtissier|ghost'])->group(function () {
        Route::livewire('/production/calendar', 'pages::production.calendar')->name('production.calendar');
        Route::livewire('/recipes', 'pages::recipes.index')->name('recipes.index');

        Route::livewire('/stock', 'pages::stock.index')->name('stock.index');
        Route::livewire('/stock/consumption', 'pages::stock.consumption')->name('stock.consumption');
        Route::livewire('/stock/shopping-list', 'pages::stock.shopping-list')->name('stock.shopping-list');
        Route::livewire('/stock/efficiency', 'pages::stock.efficiency')->name('stock.efficiency');
    });

    // MODULE 4 : Approvisionnement & Fournisseurs
    Route::middleware(['role:Gérant/Admin|Chef Pâtissier|ghost'])->group(function () {
        Route::livewire('/suppliers', 'pages::suppliers.index')->name('suppliers.index');
        Route::livewire('/delivery', 'pages::delivery.index')->name('delivery.index');
    });

    // MODULE 5 : Finances & Facturation
    // Autorise : Le Gérant, le Comptable et le Caissier (pour l'enregistrement des flux de caisse)
    Route::middleware(['role:Gérant/Admin|Comptable|Caissier|ghost'])->group(function () {
        Route::livewire('/transactions', 'pages::transactions.index')->name('transactions.index');
        Route::livewire('/transactions/unpaid', 'pages::transactions.unpaid')->name('transactions.unpaid');
        Route::livewire('/invoices', 'pages::invoices.index')->name('invoices.index');
        Route::get('/invoices/{order}/pdf', [InvoiceController::class, 'preview'])
            ->name('invoices.pdf');
        Route::get('/invoices/{order}/download', [InvoiceController::class, 'download'])
            ->name('invoices.download');
    });

    // Terminal & System Logs (réservé ghost)
    Route::middleware(['role:ghost'])->group(function () {
        Route::livewire('/terminal', 'pages::terminal')->name('terminal');
    });
});

require __DIR__.'/settings.php';
