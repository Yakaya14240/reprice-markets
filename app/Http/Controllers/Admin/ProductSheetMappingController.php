<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductSheetFieldType;
use App\Http\Controllers\Controller;
use App\Models\FeedFile;
use App\Models\Language;
use App\Models\ProductSheetField;
use App\Models\ProductSheetGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductSheetMappingController extends Controller
{
    public function index(Request $request): View
    {
        $feeds = FeedFile::query()->with('snapshot')->orderBy('name')->get();
        $feedId = $request->integer('feed_file_id') ?: $feeds->first()?->id;
        $selectedFeed = $feeds->firstWhere('id', $feedId);

        $fieldsQuery = ProductSheetField::query()
            ->with(['language', 'group'])
            ->when($feedId, fn ($q) => $q->where('feed_file_id', $feedId));

        return view('admin.product-sheet.index', [
            'feeds' => $feeds,
            'selectedFeedId' => $feedId,
            'selectedFeed' => $selectedFeed,
            'availableHeaders' => $selectedFeed?->availableHeaders() ?? [],
            'languages' => Language::query()->active()->orderBy('sort_order')->orderBy('code')->get(),
            'fieldTypes' => ProductSheetFieldType::cases(),
            'sheetGroups' => ProductSheetGroup::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'fields' => $fieldsQuery
                ->orderByRaw('case when product_sheet_group_id is null then 1 else 0 end')
                ->orderBy('product_sheet_group_id')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function update(Request $request, FeedFile $feed): RedirectResponse
    {
        $headers = $feed->availableHeaders();

        $description = trim((string) $request->input('description_column', ''));
        $request->merge([
            'description_column' => $description !== '' ? $description : null,
        ]);

        $data = $request->validate([
            'description_column' => [
                'nullable',
                'string',
                'max:255',
                Rule::when($headers !== [], Rule::in($headers)),
            ],
            'image_columns' => ['nullable', 'array', 'max:10'],
            'image_columns.*' => [
                'string',
                'max:255',
                Rule::when($headers !== [], Rule::in($headers)),
            ],
        ]);

        $images = array_values(array_unique(array_filter(
            array_map(static fn ($c) => trim((string) $c), $data['image_columns'] ?? []),
            static fn (string $c) => $c !== '',
        )));

        $feed->update([
            'description_column' => $data['description_column'] ?? null,
            'image_columns' => $images !== [] ? $images : null,
        ]);

        return redirect()
            ->route('admin.product-sheet.index', ['feed_file_id' => $feed->id])
            ->with('status', 'Mapping fiche enregistré.');
    }
}
