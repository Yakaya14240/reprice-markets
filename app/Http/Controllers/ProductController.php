<?php

namespace App\Http\Controllers;

use App\Enums\FeedSnapshotStatus;
use App\Models\FeedFile;
use App\Models\PendingPriceChange;
use App\Models\PriceChangeLog;
use App\Services\Feed\FeedSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly FeedSnapshotService $snapshots) {}

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

        $prices = $priceColumns->map(function ($column) use ($product, $pending) {
            $current = $product[$column->csv_column] ?? null;
            $pendingChange = $pending->get($column->csv_column);

            return [
                'id' => $column->id,
                'name' => $column->name,
                'csv_column' => $column->csv_column,
                'alias' => $column->alias,
                'currency_code' => $column->currency?->code,
                'currency_symbol' => $column->currency?->symbol,
                'current_price' => $current,
                'pending_price' => $pendingChange?->new_price,
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

        return response()->json([
            'id' => $row,
            'sku' => $sku,
            'name' => (string) ($product[$nameCol] ?? ''),
            'prices' => $prices,
            'history' => $history,
        ]);
    }

    public function updatePrice(Request $request, FeedFile $feed, int $row): JsonResponse
    {
        $data = $request->validate([
            'csv_column' => ['required', 'string', 'max:255'],
            'new_price' => ['required', 'numeric', 'gt:0'],
        ]);

        $exists = $feed->priceColumns()
            ->where('csv_column', $data['csv_column'])
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            return response()->json(['message' => 'Colonne prix invalide.'], 422);
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

        $old = $product[$data['csv_column']] ?? null;

        $change = PendingPriceChange::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'feed_file_id' => $feed->id,
                'sku' => $sku,
                'csv_column' => $data['csv_column'],
            ],
            [
                'old_price' => is_numeric($old) ? $old : null,
                'new_price' => $data['new_price'],
            ],
        );

        return response()->json([
            'pending' => [
                'id' => $change->id,
                'sku' => $change->sku,
                'csv_column' => $change->csv_column,
                'old_price' => $change->old_price,
                'new_price' => $change->new_price,
            ],
        ]);
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
