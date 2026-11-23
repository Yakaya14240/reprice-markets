<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\FeedController as AdminFeedController;
use App\Http\Controllers\Admin\MarketplaceController;
use App\Http\Controllers\Admin\PriceColumnController;
use App\Http\Controllers\Admin\ProductSheetMappingController;
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
            Route::get('/products/{row}', [ProductController::class, 'show'])->whereNumber('row')->name('api.products.show');
            Route::post('/products/{row}/prices', [ProductController::class, 'updatePrice'])->whereNumber('row')->name('api.products.prices');
            Route::post('/products/{row}/prices/batch', [ProductController::class, 'updatePrices'])->whereNumber('row')->name('api.products.prices.batch');
            Route::get('/pending', [PublishController::class, 'pending'])->name('api.pending.index');
            Route::delete('/pending/{pending}', [PublishController::class, 'destroyPending'])->name('api.pending.destroy');
            Route::post('/publish', [PublishController::class, 'store'])->name('api.publish.store');
        });

        Route::get('/api/publish/status', [PublishController::class, 'status'])->name('api.publish.status');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('marketplaces', MarketplaceController::class)->except(['show']);
        Route::post('marketplaces/{marketplace}/test', [MarketplaceController::class, 'test'])->name('marketplaces.test');
        Route::get('marketplaces/{marketplace}/remote-shops', [MarketplaceController::class, 'remoteShops'])->name('marketplaces.remote-shops');
        Route::get('marketplaces/{marketplace}/remote-channels', [MarketplaceController::class, 'remoteChannels'])->name('marketplaces.remote-channels');
        Route::post('marketplaces/{marketplace}/price-mappings', [MarketplaceController::class, 'storePriceMapping'])->name('marketplaces.price-mappings.store');
        Route::delete('marketplaces/{marketplace}/price-mappings/{priceMapping}', [MarketplaceController::class, 'destroyPriceMapping'])->name('marketplaces.price-mappings.destroy');
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('feeds', AdminFeedController::class)->except(['show', 'destroy']);
        Route::post('feeds/{feed}/test', [AdminFeedController::class, 'test'])->name('feeds.test');
        Route::post('feeds/{feed}/test-write', [AdminFeedController::class, 'testWrite'])->name('feeds.test-write');
        Route::resource('currencies', CurrencyController::class)->except(['show']);
        Route::get('price-columns', [PriceColumnController::class, 'index'])->name('price-columns.index');
        Route::post('price-columns', [PriceColumnController::class, 'store'])->name('price-columns.store');
        Route::put('price-columns/{priceColumn}', [PriceColumnController::class, 'update'])->name('price-columns.update');
        Route::delete('price-columns/{priceColumn}', [PriceColumnController::class, 'destroy'])->name('price-columns.destroy');
        Route::post('price-columns/{feed}/refresh-headers', [PriceColumnController::class, 'refreshHeaders'])->name('price-columns.refresh-headers');
        Route::get('product-sheet', [ProductSheetMappingController::class, 'index'])->name('product-sheet.index');
        Route::put('product-sheet/{feed}', [ProductSheetMappingController::class, 'update'])->name('product-sheet.update');
    });
});
