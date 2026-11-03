<?php

namespace App\Http\Controllers;

use App\Enums\PushBatchStatus;
use App\Jobs\PushPricesToMarketplaceJob;
use App\Models\Marketplace;
use App\Models\PricePushBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PushController extends Controller
{
    public function index(): View
    {
        return view('pushes.index', [
            'marketplaces' => Marketplace::query()->where('is_active', true)->orderBy('name')->get(),
            'batches' => PricePushBatch::query()->with(['marketplace', 'user'])->latest()->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'marketplace_id' => ['required', 'exists:marketplaces,id'],
            'feed_file_id' => ['nullable', 'exists:feed_files,id'],
        ]);

        $batch = PricePushBatch::query()->create([
            'user_id' => $request->user()->id,
            'marketplace_id' => $data['marketplace_id'],
            'feed_file_id' => $data['feed_file_id'] ?? null,
            'status' => PushBatchStatus::Queued,
            'offers_count' => 0,
            'payload' => [],
        ]);

        PushPricesToMarketplaceJob::dispatch($batch->id);

        return back()->with('status', "Push #{$batch->id} mis en file d'attente.");
    }
}
