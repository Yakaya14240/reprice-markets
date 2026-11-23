<?php

namespace App\Http\Controllers;

use App\Enums\PushBatchStatus;
use App\Models\Marketplace;
use App\Models\PriceChangeLog;
use App\Models\PriceColumn;
use App\Models\PricePushBatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'marketplace_id' => ['nullable', 'integer', 'exists:marketplaces,id'],
            'status' => ['nullable', Rule::enum(PushBatchStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $userId = $filters['user_id'] ?? null;
        $marketplaceId = $filters['marketplace_id'] ?? null;
        $status = $filters['status'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $recentChanges = PriceChangeLog::query()
            ->with(['user', 'feedFile'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(20, pageName: 'changes_page')
            ->withQueryString();

        $recentPushes = PricePushBatch::query()
            ->with(['marketplace', 'user'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($marketplaceId, fn ($q) => $q->where('marketplace_id', $marketplaceId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(20, pageName: 'pushes_page')
            ->withQueryString();

        $columnLabels = PriceColumn::query()
            ->get(['feed_file_id', 'csv_column', 'name'])
            ->mapWithKeys(fn (PriceColumn $column): array => [
                $column->feed_file_id.'|'.$column->csv_column => $column->name,
            ]);

        $today = Carbon::today();

        $filtersActive = filled($userId) || filled($marketplaceId) || filled($status) || filled($from) || filled($to);

        return view('dashboard', [
            'changesToday' => PriceChangeLog::query()->whereDate('created_at', $today)->count(),
            'pushesToday' => PricePushBatch::query()->whereDate('created_at', $today)->count(),
            'pushesFailedToday' => PricePushBatch::query()
                ->whereDate('created_at', $today)
                ->where('status', PushBatchStatus::Failed)
                ->count(),
            'activeMarketplaces' => Marketplace::query()->where('is_active', true)->count(),
            'recentChanges' => $recentChanges,
            'recentPushes' => $recentPushes,
            'columnLabels' => $columnLabels,
            'filterUsers' => User::query()->orderBy('name')->get(['id', 'name']),
            'filterMarketplaces' => Marketplace::query()->orderBy('name')->get(['id', 'name']),
            'filterStatuses' => PushBatchStatus::cases(),
            'filtersActive' => $filtersActive,
            'filters' => [
                'user_id' => $userId,
                'marketplace_id' => $marketplaceId,
                'status' => $status,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}
