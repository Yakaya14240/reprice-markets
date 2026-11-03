<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedFile;
use App\Models\PriceColumn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceColumnController extends Controller
{
    public function index(Request $request): View
    {
        $feeds = FeedFile::query()->orderBy('name')->get();
        $feedId = $request->integer('feed_file_id') ?: $feeds->first()?->id;

        return view('admin.price-columns.index', [
            'feeds' => $feeds,
            'selectedFeedId' => $feedId,
            'columns' => PriceColumn::query()
                ->when($feedId, fn ($q) => $q->where('feed_file_id', $feedId))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'feed_file_id' => ['required', 'exists:feed_files,id'],
            'name' => ['required', 'string', 'max:255'],
            'csv_column' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
        ]);

        PriceColumn::query()->create([
            ...$data,
            'is_active' => true,
        ]);

        return back()->with('status', 'Colonne prix ajoutée.');
    }

    public function update(Request $request, PriceColumn $priceColumn): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'csv_column' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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
}
