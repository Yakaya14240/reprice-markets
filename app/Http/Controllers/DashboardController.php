<?php

namespace App\Http\Controllers;

use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\PriceChangeLog;
use App\Models\PricePushBatch;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'marketplacesCount' => Marketplace::query()->where('is_active', true)->count(),
            'feedsCount' => FeedFile::query()->where('is_active', true)->count(),
            'recentChanges' => PriceChangeLog::query()->with('user')->latest()->limit(10)->get(),
            'recentPushes' => PricePushBatch::query()->with(['marketplace', 'user'])->latest()->limit(10)->get(),
        ]);
    }
}
