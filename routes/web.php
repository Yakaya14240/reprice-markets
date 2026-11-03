<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\FeedController as AdminFeedController;
use App\Http\Controllers\Admin\MarketplaceController;
use App\Http\Controllers\Admin\PriceColumnController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublishController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('prices.index')
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
        Route::get('/prices', [PriceController::class, 'index'])->name('prices.index');

        Route::prefix('api/feeds/{feed}')->group(function () {
            Route::get('/status', [ProductController::class, 'status'])->name('api.feeds.status');
            Route::post('/refresh', [ProductController::class, 'refresh'])->name('api.feeds.refresh');
            Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
            Route::get('/products/{sku}', [ProductController::class, 'show'])->name('api.products.show');
            Route::post('/products/{sku}/prices', [ProductController::class, 'updatePrice'])->name('api.products.prices');
            Route::get('/pending', [PublishController::class, 'pending'])->name('api.pending.index');
            Route::delete('/pending/{pending}', [PublishController::class, 'destroyPending'])->name('api.pending.destroy');
            Route::post('/publish', [PublishController::class, 'store'])->name('api.publish.store');
        });

        Route::get('/api/publish/status', [PublishController::class, 'status'])->name('api.publish.status');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('marketplaces', MarketplaceController::class)->except(['show', 'destroy']);
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('feeds', AdminFeedController::class)->except(['show', 'destroy']);
        Route::get('price-columns', [PriceColumnController::class, 'index'])->name('price-columns.index');
        Route::post('price-columns', [PriceColumnController::class, 'store'])->name('price-columns.store');
        Route::put('price-columns/{priceColumn}', [PriceColumnController::class, 'update'])->name('price-columns.update');
        Route::delete('price-columns/{priceColumn}', [PriceColumnController::class, 'destroy'])->name('price-columns.destroy');
    });
});
