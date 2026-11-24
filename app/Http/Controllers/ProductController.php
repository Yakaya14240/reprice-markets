<?php

namespace App\Http\Controllers;

use App\Enums\FeedSnapshotStatus;
use App\Models\FeedFile;
use App\Models\PendingPriceChange;
use App\Models\PriceChangeLog;
use App\Services\Feed\FeedSnapshotService;
use App\Services\Publish\PendingConflictDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly FeedSnapshotService $snapshots,
        private readonly PendingConflictDetector $conflictDetector,
    ) {}

    public function index(Request $request, FeedFile $feed): JsonResponse
    {
        $snapshot = $this->snapshots->ensureSnapshot($feed);
        if ($snapshot->status !== FeedSnapshotStatus::Ready) {
            return response()->json([
                'status' => $snapshot->status->value,
                'progress' => $snapshot->progress,
                'label' => $snapshot->progress_label,
                'products' => [],
                'has_more' => false,
            ]);
        }

        $data = $this->snapshots->loadProducts($feed);
        $headers = $data['headers'];
        $skuCol = $feed->resolveSkuColumn($headers);
        $nameCol = $feed->resolveNameColumn($headers);
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $offset = max(0, $request->integer('offset'));
        $limit = min(100, max(1, $request->integer('limit', 40)));

        $filtered = collect($data['products'])
            ->values()
            ->map(function (array $product, int $index) use ($feed, $headers, $nameCol) {
                return [
                    'id' => $index,
                    'sku' => $feed->productSku($product, $headers),
                    'name' => (string) ($product[$nameCol] ?? ''),
                    '_product' => $product,
                ];
            })
            ->filter(function (array $row) use ($q) {
                if ($q === '') {
                    return true;
                }

                return str_contains(mb_strtolower($row['sku']), $q)
                    || str_contains(mb_strtolower($row['name']), $q);
            })
            ->values();

        $slice = $filtered->slice($offset, $limit)->values()->map(fn (array $row) => [
            'id' => $row['id'],
            'sku' => $row['sku'],
            'name' => $row['name'],
        ]);

        return response()->json([
            'status' => 'ready',
            'sku_column' => $skuCol,
            'name_column' => $nameCol,
            'total' => $filtered->count(),
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $limit) < $filtered->count(),
            'products' => $slice,
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

        $priceColumns = $feed->priceColumns()
            ->with('currency')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $pending = PendingPriceChange::query()
            ->where('user_id', $request->user()->id)
            ->where('feed_file_id', $feed->id)
            ->where('sku', $sku)
            ->get()
            ->keyBy('csv_column');

        $othersByColumn = collect($this->conflictDetector->othersForSku($request->user(), $feed, $sku))
            ->groupBy('csv_column');

        $prices = $priceColumns->map(function ($column) use ($product, $pending, $othersByColumn, $feed) {
            $currentRaw = $product[$column->csv_column] ?? null;
            $pendingChange = $pending->get($column->csv_column);
            $others = ($othersByColumn->get($column->csv_column) ?? collect())
                ->map(fn (array $change) => [
                    'user_id' => $change['user_id'],
                    'user_name' => $change['user_name'],
                    'new_price' => $change['new_price'],
                ])
                ->values()
                ->all();

            return [
                'id' => $column->id,
                'name' => $column->name,
                'csv_column' => $column->csv_column,
                'alias' => $column->alias,
                'currency_code' => $column->currency?->code,
                'currency_symbol' => $column->currency?->symbol,
                'current_price' => $feed->priceForInput($currentRaw),
                'current_price_raw' => $currentRaw,
                'pending_price' => $pendingChange?->new_price,
                'others_pending' => $others,
            ];
        });

        $history = PriceChangeLog::query()
            ->with('user')
            ->where('feed_file_id', $feed->id)
            ->where('sku', $sku)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (PriceChangeLog $log) => [
                'csv_column' => $log->csv_column,
                'old_price' => $log->old_price,
                'new_price' => $log->new_price,
                'user' => $log->user?->name,
                'at' => $log->created_at?->toIso8601String(),
            ]);

        $chartDefault = $priceColumns->firstWhere('is_chart_default', true)
            ?? $priceColumns->first();

        return response()->json([
            'id' => $row,
            'sku' => $sku,
            'name' => (string) ($product[$nameCol] ?? ''),
            'description' => $feed->productDescription($product, $headers),
            'images' => $feed->productImages($product, $headers),
            'prices' => $prices,
            'history' => $history,
            'chart_default_csv_column' => $chartDefault?->csv_column,
            'chart_default_name' => $chartDefault?->name,
        ]);
    }

    public function updatePrice(Request $request, FeedFile $feed, int $row): JsonResponse
    {
        $data = $request->validate([
            'csv_column' => ['required', 'string', 'max:255'],
            'new_price' => ['required', 'numeric', 'gt:0'],
            'confirm_conflicts' => ['sometimes', 'boolean'],
        ]);

        $changes = $this->storePendingPrices(
            $request,
            $feed,
            $row,
            [
                [
                    'csv_column' => $data['csv_column'],
                    'new_price' => $data['new_price'],
                ],
            ],
            (bool) ($data['confirm_conflicts'] ?? false),
        );

        if ($changes instanceof JsonResponse) {
            return $changes;
        }

        return response()->json([
            'pending' => $changes[0] ?? null,
        ]);
    }

    public function updatePrices(Request $request, FeedFile $feed, int $row): JsonResponse
    {
        $data = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.csv_column' => ['required', 'string', 'max:255'],
            'prices.*.new_price' => ['required', 'numeric', 'gt:0'],
            'confirm_conflicts' => ['sometimes', 'boolean'],
        ]);

        $changes = $this->storePendingPrices(
            $request,
            $feed,
            $row,
            $data['prices'],
            (bool) ($data['confirm_conflicts'] ?? false),
        );

        if ($changes instanceof JsonResponse) {
            return $changes;
        }

        return response()->json([
            'pending' => $changes,
            'count' => count($changes),
        ]);
    }

    /**
     * @param  list<array{csv_column: string, new_price: mixed}>  $items
     * @return list<array{id: int, sku: string, csv_column: string, old_price: mixed, new_price: mixed}>|JsonResponse
     */
    private function storePendingPrices(
        Request $request,
        FeedFile $feed,
        int $row,
        array $items,
        bool $confirmConflicts = false,
    ): array|JsonResponse {
        $allowedColumns = $feed->priceColumns()
            ->where('is_active', true)
            ->pluck('csv_column')
            ->all();

        foreach ($items as $item) {
            if (! in_array($item['csv_column'], $allowedColumns, true)) {
                return response()->json(['message' => 'Colonne prix invalide : '.$item['csv_column']], 422);
            }
        }

        $loaded = $this->snapshots->loadProducts($feed);
        $product = $loaded['products'][$row] ?? null;
        if (! is_array($product)) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        $sku = $feed->productSku($product, $loaded['headers']);
        if ($sku === '') {
            return response()->json([
                'message' => 'Impossible de déterminer le SKU du produit. Vérifiez la colonne SKU du flux en admin (ex. Identifiant_unique).',
            ], 422);
        }

        if (! $confirmConflicts) {
            $conflicts = $this->conflictDetector->detectForProposed($request->user(), $feed, $sku, $items);
            if ($conflicts !== []) {
                return response()->json([
                    'code' => 'pending_conflicts',
                    'message' => 'Des collègues ont déjà des modifications en attente sur ces prix.',
                    'conflicts' => $conflicts,
                ], 409);
            }
        }

        $pending = [];
        foreach ($items as $item) {
            $old = $feed->parsePrice($product[$item['csv_column']] ?? null);
            $change = PendingPriceChange::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'feed_file_id' => $feed->id,
                    'sku' => $sku,
                    'csv_column' => $item['csv_column'],
                ],
                [
                    'old_price' => $old,
                    'new_price' => $item['new_price'],
                ],
            );

            $pending[] = [
                'id' => $change->id,
                'sku' => $change->sku,
                'csv_column' => $change->csv_column,
                'old_price' => $change->old_price,
                'new_price' => $change->new_price,
            ];
        }

        return $pending;
    }

    public function refresh(FeedFile $feed): JsonResponse
    {
        try {
            $snapshot = $this->snapshots->refresh($feed);
        } catch (\Throwable $e) {
            $snapshot = $feed->snapshot()->first();

            return response()->json([
                'status' => $snapshot?->status?->value ?? 'failed',
                'progress' => $snapshot?->progress ?? 100,
                'label' => $snapshot?->progress_label ?? 'Échec du chargement',
                'products_count' => $snapshot?->products_count ?? 0,
                'error' => $snapshot?->error_message ?: $e->getMessage(),
                'message' => $snapshot?->error_message ?: $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => $snapshot->status->value,
            'progress' => $snapshot->progress,
            'label' => $snapshot->progress_label,
            'products_count' => $snapshot->products_count,
            'error' => $snapshot->error_message,
        ]);
    }

    public function status(FeedFile $feed): JsonResponse
    {
        $snapshot = $this->snapshots->ensureSnapshot($feed);

        return response()->json([
            'status' => $snapshot->status->value,
            'progress' => $snapshot->progress,
            'label' => $snapshot->progress_label,
            'products_count' => $snapshot->products_count,
            'loaded_at' => $snapshot->loaded_at?->toIso8601String(),
            'error' => $snapshot->error_message,
        ]);
    }
}
