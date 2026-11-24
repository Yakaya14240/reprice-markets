<?php

namespace App\Http\Controllers;

use App\Models\FeedFile;
use App\Models\Language;
use App\Models\Marketplace;
use App\Models\PendingSheetChange;
use App\Models\ProductSheetField;
use App\Services\Feed\FeedSnapshotService;
use App\Services\Publish\PendingSheetConflictDetector;
use App\Services\Sheet\ProductSheetFieldValueValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SheetController extends Controller
{
    public function __construct(
        private readonly FeedSnapshotService $snapshots,
        private readonly PendingSheetConflictDetector $conflictDetector,
        private readonly ProductSheetFieldValueValidator $valueValidator,
    ) {}

    public function index(Request $request): View
    {
        $feeds = FeedFile::query()->where('is_active', true)->orderBy('name')->get();
        $feedId = $request->integer('feed_file_id') ?: $feeds->first()?->id;
        $feed = $feeds->firstWhere('id', $feedId);

        $pending = collect();
        if ($feed) {
            $pending = PendingSheetChange::query()
                ->with('field:id,label')
                ->where('user_id', $request->user()->id)
                ->where('feed_file_id', $feed->id)
                ->latest()
                ->get();
        }

        return view('sheets.index', [
            'feeds' => $feeds,
            'feed' => $feed,
            'pending' => $pending,
            'languages' => Language::query()->active()->orderBy('sort_order')->orderBy('code')->get(),
            'marketplaces' => Marketplace::query()->where('is_active', true)->orderBy('name')->get(),
            'bootRefresh' => (bool) session('boot_refresh'),
            'writeDestination' => $feed?->writeDestinationLabel() ?? 'non configurée',
            'canWrite' => (bool) $feed?->hasWritableDestination(),
        ]);
    }

    public function show(Request $request, FeedFile $feed, int $row): JsonResponse
    {
        $data = $this->snapshots->loadProducts($feed);
        $product = $data['products'][$row] ?? null;

        if (! is_array($product)) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $headers = $data['headers'];
        $sku = $feed->productSku($product, $headers);
        $nameCol = $feed->resolveNameColumn($headers);

        $languages = Language::query()->active()->orderBy('sort_order')->orderBy('code')->get();
        $langCode = strtoupper(trim((string) $request->query('lang', $languages->first()?->code ?? 'FR')));
        $language = $languages->firstWhere('code', $langCode) ?? $languages->first();

        $fieldsQuery = ProductSheetField::query()
            ->with('group')
            ->where('feed_file_id', $feed->id)
            ->orderBy('sort_order')
            ->orderBy('label');

        if ($language) {
            $fieldsQuery->where('language_id', $language->id);
        }

        $fields = $fieldsQuery->get();

        $pending = PendingSheetChange::query()
            ->where('user_id', $request->user()->id)
            ->where('feed_file_id', $feed->id)
            ->where('sku', $sku)
            ->get()
            ->keyBy('product_sheet_field_id');

        $othersByField = collect($this->conflictDetector->othersForSku($request->user(), $feed, $sku))
            ->groupBy('product_sheet_field_id');

        $payloadFields = $fields->map(function (ProductSheetField $field) use ($product, $pending, $othersByField) {
            $current = (string) ($product[$field->csv_column] ?? '');
            $pendingChange = $pending->get($field->id);
            $others = ($othersByField->get($field->id) ?? collect())
                ->map(fn (array $change) => [
                    'user_id' => $change['user_id'],
                    'user_name' => $change['user_name'],
                    'new_value' => $change['new_value'],
                ])
                ->values()
                ->all();

            return [
                'id' => $field->id,
                'label' => $field->label,
                'group_id' => $field->product_sheet_group_id,
                'group_name' => $field->groupLabel(),
                'group_sort_order' => $field->group?->sort_order ?? 9999,
                'type' => $field->type->value,
                'type_label' => $field->type->label(),
                'csv_column' => $field->csv_column,
                'is_editable' => $field->is_editable,
                'max_length' => $field->max_length,
                'help_text' => $field->help_text,
                'sort_order' => $field->sort_order,
                'max_images' => $field->type->value === 'images' ? $field->maxImages() : null,
                'current_value' => $current,
                'pending_value' => $pendingChange?->new_value,
                'others_pending' => $others,
            ];
        });

        $groups = $payloadFields
            ->groupBy('group_name')
            ->map(function ($items, string $name) {
                $sorted = $items->sortBy([
                    ['sort_order', 'asc'],
                    ['label', 'asc'],
                ])->values();

                return [
                    'name' => $name,
                    'sort_order' => (int) ($sorted->first()['group_sort_order'] ?? 9999),
                    'fields' => $sorted->all(),
                ];
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        return response()->json([
            'id' => $row,
            'sku' => $sku,
            'name' => (string) ($product[$nameCol] ?? ''),
            'language' => $language ? [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
            ] : null,
            'languages' => $languages->map(fn (Language $lang) => [
                'id' => $lang->id,
                'code' => $lang->code,
                'name' => $lang->name,
            ])->values(),
            'fields' => $payloadFields->values(),
            'groups' => $groups,
        ]);
    }

    public function updateFields(Request $request, FeedFile $feed, int $row): JsonResponse
    {
        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.product_sheet_field_id' => ['required', 'integer', 'exists:product_sheet_fields,id'],
            'fields.*.new_value' => ['nullable', 'string'],
            'confirm_conflicts' => ['sometimes', 'boolean'],
        ]);

        $loaded = $this->snapshots->loadProducts($feed);
        $product = $loaded['products'][$row] ?? null;
        if (! is_array($product)) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $sku = $feed->productSku($product, $loaded['headers']);
        if ($sku === '') {
            return response()->json([
                'message' => 'Impossible de déterminer le SKU du produit. Vérifiez la colonne SKU du flux en admin.',
            ], 422);
        }

        $fieldIds = collect($data['fields'])->pluck('product_sheet_field_id')->map(fn ($id) => (int) $id)->all();
        $fields = ProductSheetField::query()
            ->where('feed_file_id', $feed->id)
            ->whereIn('id', $fieldIds)
            ->get()
            ->keyBy('id');

        $normalizedItems = [];
        foreach ($data['fields'] as $item) {
            $fieldId = (int) $item['product_sheet_field_id'];
            $field = $fields->get($fieldId);
            if (! $field) {
                return response()->json(['message' => 'Champ fiche invalide : #'.$fieldId], 422);
            }
            if (! $field->is_editable) {
                return response()->json(['message' => "Le champ « {$field->label} » n’est pas éditable."], 422);
            }

            try {
                $newValue = $this->valueValidator->normalize($field, $item['new_value'] ?? '');
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            $normalizedItems[] = [
                'field' => $field,
                'product_sheet_field_id' => $fieldId,
                'new_value' => $newValue,
            ];
        }

        $confirmConflicts = (bool) ($data['confirm_conflicts'] ?? false);
        if (! $confirmConflicts) {
            $conflicts = $this->conflictDetector->detectForProposed(
                $request->user(),
                $feed,
                $sku,
                array_map(fn (array $item) => [
                    'product_sheet_field_id' => $item['product_sheet_field_id'],
                    'new_value' => $item['new_value'],
                ], $normalizedItems),
            );
            if ($conflicts !== []) {
                return response()->json([
                    'code' => 'pending_conflicts',
                    'message' => 'Des collègues ont déjà des modifications en attente sur ces champs.',
                    'conflicts' => $conflicts,
                ], 409);
            }
        }

        $pending = [];
        foreach ($normalizedItems as $item) {
            /** @var ProductSheetField $field */
            $field = $item['field'];
            $old = (string) ($product[$field->csv_column] ?? '');
            $change = PendingSheetChange::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'feed_file_id' => $feed->id,
                    'sku' => $sku,
                    'product_sheet_field_id' => $field->id,
                ],
                [
                    'old_value' => $old,
                    'new_value' => $item['new_value'],
                ],
            );

            $pending[] = [
                'id' => $change->id,
                'sku' => $change->sku,
                'product_sheet_field_id' => $change->product_sheet_field_id,
                'field_label' => $field->label,
                'old_value' => $change->old_value,
                'new_value' => $change->new_value,
            ];
        }

        return response()->json([
            'pending' => $pending,
            'count' => count($pending),
        ]);
    }
}
