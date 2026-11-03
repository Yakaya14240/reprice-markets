<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\FeedFile;
use App\Models\PriceColumn;
use App\Services\Feed\FeedSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceColumnController extends Controller
{
    public function index(Request $request): View
    {
        $feeds = FeedFile::query()->with('snapshot')->orderBy('name')->get();
        $feedId = $request->integer('feed_file_id') ?: $feeds->first()?->id;
        $selectedFeed = $feeds->firstWhere('id', $feedId);

        return view('admin.price-columns.index', [
            'feeds' => $feeds,
            'selectedFeedId' => $feedId,
            'selectedFeed' => $selectedFeed,
            'availableHeaders' => $selectedFeed?->availableHeaders() ?? [],
            'currencies' => Currency::query()->active()->orderBy('sort_order')->orderBy('code')->get(),
            'columns' => PriceColumn::query()
                ->with('currency')
                ->when($feedId, fn ($q) => $q->where('feed_file_id', $feedId))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $feed = FeedFile::query()->with('snapshot')->findOrFail($request->integer('feed_file_id'));
        $data = $this->validated($request, $feed);

        PriceColumn::query()->create([
            ...$data,
            'feed_file_id' => $feed->id,
            'is_active' => true,
        ]);

        return back()->with('status', 'Colonne prix ajoutée.');
    }

    public function update(Request $request, PriceColumn $priceColumn): RedirectResponse
    {
        $feed = $priceColumn->feedFile()->with('snapshot')->firstOrFail();
        $data = $this->validated($request, $feed);

        $priceColumn->update([
            ...$data,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Colonne prix mise à jour.');
    }

    public function destroy(PriceColumn $priceColumn): RedirectResponse
    {
        $priceColumn->delete();

        return back()->with('status', 'Colonne prix supprimée.');
    }

    public function refreshHeaders(FeedFile $feed, FeedSnapshotService $snapshots): RedirectResponse
    {
        try {
            $snapshots->refresh($feed);
        } catch (\Throwable $e) {
            return back()->with('status', 'Échec du chargement des colonnes : '.$e->getMessage());
        }

        $count = count($feed->fresh('snapshot')->availableHeaders());

        return redirect()
            ->route('admin.price-columns.index', ['feed_file_id' => $feed->id])
            ->with('status', "Colonnes CSV rechargées ({$count}).");
    }

    /**
     * @return array{name: string, csv_column: string, alias: ?string, currency_id: int}
     */
    private function validated(Request $request, FeedFile $feed): array
    {
        $headers = $feed->availableHeaders();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'csv_column' => [
                'required',
                'string',
                'max:255',
                Rule::when($headers !== [], Rule::in($headers)),
            ],
            'alias' => ['nullable', 'string', 'max:255'],
            'currency_id' => ['required', 'exists:currencies,id'],
        ]);
    }
}
