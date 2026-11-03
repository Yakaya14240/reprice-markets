<?php

namespace App\Http\Controllers;

use App\Models\FeedFile;
use App\Models\PriceChangeLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Price editing UI will load products from SFTP in a later iteration.
 * For now we expose the audit trail + placeholder search screen.
 */
class PriceController extends Controller
{
    public function index(Request $request): View
    {
        return view('prices.index', [
            'feeds' => FeedFile::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedFeedId' => $request->integer('feed_file_id') ?: null,
            'logs' => PriceChangeLog::query()
                ->with(['user', 'feedFile'])
                ->when(
                    $request->filled('feed_file_id'),
                    fn ($q) => $q->where('feed_file_id', $request->integer('feed_file_id')),
                )
                ->latest()
                ->paginate(25),
        ]);
    }

    public function storeLog(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'feed_file_id' => ['required', 'exists:feed_files,id'],
            'sku' => ['required', 'string', 'max:100'],
            'csv_column' => ['required', 'string', 'max:255'],
            'old_price' => ['nullable', 'numeric'],
            'new_price' => ['required', 'numeric', 'gt:0'],
        ]);

        PriceChangeLog::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Modification de prix enregistrée (audit). Le push SFTP/API arrivera ensuite.');
    }
}
