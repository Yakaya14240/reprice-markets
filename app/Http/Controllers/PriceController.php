<?php

namespace App\Http\Controllers;

use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\PendingPriceChange;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceController extends Controller
{
    public function index(Request $request): View
    {
        $feeds = FeedFile::query()->where('is_active', true)->orderBy('name')->get();
        $feedId = $request->integer('feed_file_id') ?: $feeds->first()?->id;
        $feed = $feeds->firstWhere('id', $feedId);

        $pending = collect();
        if ($feed) {
            $pending = PendingPriceChange::query()
                ->where('user_id', $request->user()->id)
                ->where('feed_file_id', $feed->id)
                ->latest()
                ->get();
        }

        return view('prices.index', [
            'feeds' => $feeds,
            'feed' => $feed,
            'pending' => $pending,
            'marketplaces' => Marketplace::query()->where('is_active', true)->orderBy('name')->get(),
            'bootRefresh' => (bool) session('boot_refresh'),
        ]);
    }
}
