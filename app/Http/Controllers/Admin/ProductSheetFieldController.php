<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductSheetFieldType;
use App\Http\Controllers\Controller;
use App\Models\FeedFile;
use App\Models\Language;
use App\Models\ProductSheetField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductSheetFieldController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $feed = FeedFile::query()->with('snapshot')->findOrFail($request->integer('feed_file_id'));
        $data = $this->validated($request, $feed);

        ProductSheetField::query()->create([
            ...$data,
            'feed_file_id' => $feed->id,
        ]);

        return redirect()
            ->route('admin.product-sheet.index', ['feed_file_id' => $feed->id])
            ->with('status', 'Champ fiche ajouté.');
    }

    public function update(Request $request, ProductSheetField $productSheetField): RedirectResponse
    {
        $feed = $productSheetField->feedFile()->with('snapshot')->firstOrFail();
        $data = $this->validated($request, $feed);

        $productSheetField->update($data);

        return redirect()
            ->route('admin.product-sheet.index', ['feed_file_id' => $feed->id])
            ->with('status', 'Champ fiche mis à jour.');
    }

    public function destroy(ProductSheetField $productSheetField): RedirectResponse
    {
        $feedId = $productSheetField->feed_file_id;
        $productSheetField->delete();

        return redirect()
            ->route('admin.product-sheet.index', ['feed_file_id' => $feedId])
            ->with('status', 'Champ fiche supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, FeedFile $feed): array
    {
        $headers = $feed->availableHeaders();
        $types = array_column(ProductSheetFieldType::cases(), 'value');

        $data = $request->validate([
            'language_id' => ['required', 'exists:languages,id'],
            'type' => ['required', Rule::in($types)],
            'label' => ['required', 'string', 'max:255'],
            'product_sheet_group_id' => [
                'nullable',
                'integer',
                'exists:product_sheet_groups,id',
            ],
            'csv_column' => [
                'required',
                'string',
                'max:255',
                Rule::when($headers !== [], Rule::in($headers)),
            ],
            'is_editable' => ['sometimes', 'boolean'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'max_images' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        Language::query()->findOrFail((int) $data['language_id']);

        $type = ProductSheetFieldType::from($data['type']);
        $settings = null;
        if ($type === ProductSheetFieldType::Images) {
            $settings = [
                'max_images' => (int) ($data['max_images'] ?? 10),
            ];
        }

        $maxLength = $data['max_length'] ?? null;
        if (! $type->allowsMaxLength()) {
            $maxLength = null;
        }

        $groupId = $data['product_sheet_group_id'] ?? null;

        return [
            'language_id' => (int) $data['language_id'],
            'type' => $type,
            'label' => $data['label'],
            'product_sheet_group_id' => $groupId ? (int) $groupId : null,
            'csv_column' => $data['csv_column'],
            'is_editable' => $request->boolean('is_editable'),
            'max_length' => $maxLength !== null ? (int) $maxLength : null,
            'help_text' => filled($data['help_text'] ?? null) ? trim((string) $data['help_text']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 100),
            'settings' => $settings,
        ];
    }
}
