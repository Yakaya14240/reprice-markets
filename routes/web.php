<?php

use App\Http\Controllers\Admin\MarketplaceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\PushController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:operator,admin')->group(function () {
        Route::get('/feeds', [FeedController::class, 'index'])->name('feeds.index');
        Route::get('/feeds/{feed}', [FeedController::class, 'show'])->name('feeds.show');

        Route::get('/prices', [PriceController::class, 'index'])->name('prices.index');
        Route::post('/prices/log', [PriceController::class, 'storeLog'])->name('prices.log');

        Route::get('/pushes', [PushController::class, 'index'])->name('pushes.index');
        Route::post('/pushes', [PushController::class, 'store'])->name('pushes.store');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('marketplaces', MarketplaceController::class)->except(['show', 'destroy']);
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
    });
});
