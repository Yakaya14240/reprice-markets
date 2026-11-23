<?php

namespace App\Services\Publish;

use App\Models\FeedFile;
use App\Models\PendingPriceChange;
use App\Models\User;
use Illuminate\Support\Collection;

final class PendingConflictDetector
{
    /**
     * Detect pending price changes from other users that overlap this user's basket
     * on the same feed (same sku + csv_column).
     *
     * @return list<array{
     *     sku: string,
     *     csv_column: string,
     *     other_user_id: int,
     *     other_user_name: string,
     *     other_new_price: string,
     *     my_new_price: string
     * }>
     */
    public function detect(User $user, FeedFile $feed): array
    {
        $mine = PendingPriceChange::query()
            ->where('user_id', $user->id)
            ->where('feed_file_id', $feed->id)
            ->get();

        if ($mine->isEmpty()) {
            return [];
        }

        $mineByKey = $mine->keyBy(
            fn (PendingPriceChange $change): string => $this->pairKey($change->sku, $change->csv_column)
        );

        return $this->mapOverlaps(
            $this->othersMatchingPairs($feed, $user->id, $mine),
            $mineByKey,
        );
    }

    /**
     * Detect overlaps between proposed price edits and other users' pending changes.
     *
     * @param  list<array{csv_column: string, new_price: mixed}>  $items
     * @return list<array{
     *     sku: string,
     *     csv_column: string,
     *     other_user_id: int,
     *     other_user_name: string,
     *     other_new_price: string,
     *     my_new_price: string
     * }>
     */
    public function detectForProposed(User $user, FeedFile $feed, string $sku, array $items): array
    {
        if ($sku === '' || $items === []) {
            return [];
        }

        $csvColumns = collect($items)
            ->pluck('csv_column')
            ->filter(fn (mixed $column): bool => is_string($column) && $column !== '')
            ->unique()
            ->values();

        if ($csvColumns->isEmpty()) {
            return [];
        }

        $proposedByKey = collect($items)
            ->filter(fn (array $item): bool => isset($item['csv_column']) && is_string($item['csv_column']))
            ->keyBy(fn (array $item): string => $this->pairKey($sku, $item['csv_column']));

        $others = PendingPriceChange::query()
            ->where('feed_file_id', $feed->id)
            ->where('user_id', '!=', $user->id)
            ->where('sku', $sku)
            ->whereIn('csv_column', $csvColumns->all())
            ->with(['user:id,name'])
            ->get();

        $conflicts = [];
        foreach ($others as $other) {
            $key = $this->pairKey($other->sku, $other->csv_column);
            $proposed = $proposedByKey->get($key);
            if ($proposed === null) {
                continue;
            }

            $conflicts[] = [
                'sku' => $other->sku,
                'csv_column' => $other->csv_column,
                'other_user_id' => (int) $other->user_id,
                'other_user_name' => $other->user?->name ?? 'Utilisateur #'.$other->user_id,
                'other_new_price' => (string) $other->new_price,
                'my_new_price' => (string) ($proposed['new_price'] ?? ''),
            ];
        }

        return $conflicts;
    }

    /**
     * List other operators' pending changes for a feed (read-only awareness).
     *
     * @return list<array{
     *     id: int,
     *     sku: string,
     *     csv_column: string,
     *     old_price: mixed,
     *     new_price: mixed,
     *     user_id: int,
     *     user_name: string,
     *     updated_at: string|null
     * }>
     */
    public function othersForFeed(User $user, FeedFile $feed): array
    {
        return $this->serializeOthers(
            PendingPriceChange::query()
                ->where('feed_file_id', $feed->id)
                ->where('user_id', '!=', $user->id)
                ->with(['user:id,name'])
                ->orderByDesc('updated_at')
                ->get()
        );
    }

    /**
     * List other operators' pending changes for one SKU.
     *
     * @return list<array{
     *     id: int,
     *     sku: string,
     *     csv_column: string,
     *     old_price: mixed,
     *     new_price: mixed,
     *     user_id: int,
     *     user_name: string,
     *     updated_at: string|null
     * }>
     */
    public function othersForSku(User $user, FeedFile $feed, string $sku): array
    {
        if ($sku === '') {
            return [];
        }

        return $this->serializeOthers(
            PendingPriceChange::query()
                ->where('feed_file_id', $feed->id)
                ->where('user_id', '!=', $user->id)
                ->where('sku', $sku)
                ->with(['user:id,name'])
                ->orderByDesc('updated_at')
                ->get()
        );
    }

    /**
     * @param  Collection<int, PendingPriceChange>  $pairs
     * @return Collection<int, PendingPriceChange>
     */
    private function othersMatchingPairs(FeedFile $feed, int $userId, Collection $pairs): Collection
    {
        if ($pairs->isEmpty()) {
            return collect();
        }

        return PendingPriceChange::query()
            ->where('feed_file_id', $feed->id)
            ->where('user_id', '!=', $userId)
            ->where(function ($query) use ($pairs): void {
                foreach ($pairs as $change) {
                    $query->orWhere(function ($inner) use ($change): void {
                        $inner->where('sku', $change->sku)
                            ->where('csv_column', $change->csv_column);
                    });
                }
            })
            ->with(['user:id,name'])
            ->get();
    }

    /**
     * @param  Collection<int, PendingPriceChange>  $others
     * @param  Collection<string, PendingPriceChange>  $mineByKey
     * @return list<array{
     *     sku: string,
     *     csv_column: string,
     *     other_user_id: int,
     *     other_user_name: string,
     *     other_new_price: string,
     *     my_new_price: string
     * }>
     */
    private function mapOverlaps(Collection $others, Collection $mineByKey): array
    {
        $conflicts = [];

        foreach ($others as $other) {
            $key = $this->pairKey($other->sku, $other->csv_column);
            $mineChange = $mineByKey->get($key);
            if ($mineChange === null) {
                continue;
            }

            $conflicts[] = [
                'sku' => $other->sku,
                'csv_column' => $other->csv_column,
                'other_user_id' => (int) $other->user_id,
                'other_user_name' => $other->user?->name ?? 'Utilisateur #'.$other->user_id,
                'other_new_price' => (string) $other->new_price,
                'my_new_price' => (string) ($mineChange->new_price ?? ''),
            ];
        }

        return $conflicts;
    }

    /**
     * @param  Collection<int, PendingPriceChange>  $others
     * @return list<array{
     *     id: int,
     *     sku: string,
     *     csv_column: string,
     *     old_price: mixed,
     *     new_price: mixed,
     *     user_id: int,
     *     user_name: string,
     *     updated_at: string|null
     * }>
     */
    private function serializeOthers(Collection $others): array
    {
        return $others->map(fn (PendingPriceChange $change): array => [
            'id' => $change->id,
            'sku' => $change->sku,
            'csv_column' => $change->csv_column,
            'old_price' => $change->old_price,
            'new_price' => $change->new_price,
            'user_id' => (int) $change->user_id,
            'user_name' => $change->user?->name ?? 'Utilisateur #'.$change->user_id,
            'updated_at' => $change->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    private function pairKey(string $sku, string $csvColumn): string
    {
        return $sku.'|'.$csvColumn;
    }
}
