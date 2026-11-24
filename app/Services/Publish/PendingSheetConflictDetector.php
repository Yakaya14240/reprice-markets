<?php

namespace App\Services\Publish;

use App\Models\FeedFile;
use App\Models\PendingSheetChange;
use App\Models\User;
use Illuminate\Support\Collection;

final class PendingSheetConflictDetector
{
    /**
     * @return list<array{
     *     sku: string,
     *     product_sheet_field_id: int,
     *     field_label: string,
     *     other_user_id: int,
     *     other_user_name: string,
     *     other_new_value: string,
     *     my_new_value: string
     * }>
     */
    public function detect(User $user, FeedFile $feed): array
    {
        $mine = PendingSheetChange::query()
            ->where('user_id', $user->id)
            ->where('feed_file_id', $feed->id)
            ->with('field:id,label')
            ->get();

        if ($mine->isEmpty()) {
            return [];
        }

        $mineByKey = $mine->keyBy(
            fn (PendingSheetChange $change): string => $this->pairKey($change->sku, (int) $change->product_sheet_field_id)
        );

        return $this->mapOverlaps(
            $this->othersMatchingPairs($feed, $user->id, $mine),
            $mineByKey,
        );
    }

    /**
     * @param  list<array{product_sheet_field_id: int, new_value: mixed}>  $items
     * @return list<array{
     *     sku: string,
     *     product_sheet_field_id: int,
     *     field_label: string,
     *     other_user_id: int,
     *     other_user_name: string,
     *     other_new_value: string,
     *     my_new_value: string
     * }>
     */
    public function detectForProposed(User $user, FeedFile $feed, string $sku, array $items): array
    {
        if ($sku === '' || $items === []) {
            return [];
        }

        $fieldIds = collect($items)
            ->pluck('product_sheet_field_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($fieldIds->isEmpty()) {
            return [];
        }

        $proposedByKey = collect($items)
            ->filter(fn (array $item): bool => isset($item['product_sheet_field_id']))
            ->keyBy(fn (array $item): string => $this->pairKey($sku, (int) $item['product_sheet_field_id']));

        $others = PendingSheetChange::query()
            ->where('feed_file_id', $feed->id)
            ->where('user_id', '!=', $user->id)
            ->where('sku', $sku)
            ->whereIn('product_sheet_field_id', $fieldIds->all())
            ->with(['user:id,name', 'field:id,label'])
            ->get();

        $conflicts = [];
        foreach ($others as $other) {
            $key = $this->pairKey($other->sku, (int) $other->product_sheet_field_id);
            $proposed = $proposedByKey->get($key);
            if ($proposed === null) {
                continue;
            }

            $conflicts[] = [
                'sku' => $other->sku,
                'product_sheet_field_id' => (int) $other->product_sheet_field_id,
                'field_label' => $other->field?->label ?? ('Champ #'.$other->product_sheet_field_id),
                'other_user_id' => (int) $other->user_id,
                'other_user_name' => $other->user?->name ?? 'Utilisateur #'.$other->user_id,
                'other_new_value' => (string) $other->new_value,
                'my_new_value' => (string) ($proposed['new_value'] ?? ''),
            ];
        }

        return $conflicts;
    }

    /**
     * @return list<array{
     *     id: int,
     *     sku: string,
     *     product_sheet_field_id: int,
     *     field_label: string,
     *     old_value: mixed,
     *     new_value: mixed,
     *     user_id: int,
     *     user_name: string,
     *     updated_at: string|null
     * }>
     */
    public function othersForFeed(User $user, FeedFile $feed): array
    {
        return $this->serializeOthers(
            PendingSheetChange::query()
                ->where('feed_file_id', $feed->id)
                ->where('user_id', '!=', $user->id)
                ->with(['user:id,name', 'field:id,label'])
                ->orderByDesc('updated_at')
                ->get()
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     sku: string,
     *     product_sheet_field_id: int,
     *     field_label: string,
     *     old_value: mixed,
     *     new_value: mixed,
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
            PendingSheetChange::query()
                ->where('feed_file_id', $feed->id)
                ->where('user_id', '!=', $user->id)
                ->where('sku', $sku)
                ->with(['user:id,name', 'field:id,label'])
                ->orderByDesc('updated_at')
                ->get()
        );
    }

    /**
     * @param  Collection<int, PendingSheetChange>  $pairs
     * @return Collection<int, PendingSheetChange>
     */
    private function othersMatchingPairs(FeedFile $feed, int $userId, Collection $pairs): Collection
    {
        if ($pairs->isEmpty()) {
            return collect();
        }

        return PendingSheetChange::query()
            ->where('feed_file_id', $feed->id)
            ->where('user_id', '!=', $userId)
            ->where(function ($query) use ($pairs): void {
                foreach ($pairs as $change) {
                    $query->orWhere(function ($inner) use ($change): void {
                        $inner->where('sku', $change->sku)
                            ->where('product_sheet_field_id', $change->product_sheet_field_id);
                    });
                }
            })
            ->with(['user:id,name', 'field:id,label'])
            ->get();
    }

    /**
     * @param  Collection<int, PendingSheetChange>  $others
     * @param  Collection<string, PendingSheetChange>  $mineByKey
     * @return list<array{
     *     sku: string,
     *     product_sheet_field_id: int,
     *     field_label: string,
     *     other_user_id: int,
     *     other_user_name: string,
     *     other_new_value: string,
     *     my_new_value: string
     * }>
     */
    private function mapOverlaps(Collection $others, Collection $mineByKey): array
    {
        $conflicts = [];

        foreach ($others as $other) {
            $key = $this->pairKey($other->sku, (int) $other->product_sheet_field_id);
            $mineChange = $mineByKey->get($key);
            if ($mineChange === null) {
                continue;
            }

            $conflicts[] = [
                'sku' => $other->sku,
                'product_sheet_field_id' => (int) $other->product_sheet_field_id,
                'field_label' => $other->field?->label ?? ('Champ #'.$other->product_sheet_field_id),
                'other_user_id' => (int) $other->user_id,
                'other_user_name' => $other->user?->name ?? 'Utilisateur #'.$other->user_id,
                'other_new_value' => (string) $other->new_value,
                'my_new_value' => (string) ($mineChange->new_value ?? ''),
            ];
        }

        return $conflicts;
    }

    /**
     * @param  Collection<int, PendingSheetChange>  $others
     * @return list<array{
     *     id: int,
     *     sku: string,
     *     product_sheet_field_id: int,
     *     field_label: string,
     *     old_value: mixed,
     *     new_value: mixed,
     *     user_id: int,
     *     user_name: string,
     *     updated_at: string|null
     * }>
     */
    private function serializeOthers(Collection $others): array
    {
        return $others->map(fn (PendingSheetChange $change): array => [
            'id' => $change->id,
            'sku' => $change->sku,
            'product_sheet_field_id' => (int) $change->product_sheet_field_id,
            'field_label' => $change->field?->label ?? ('Champ #'.$change->product_sheet_field_id),
            'old_value' => $change->old_value,
            'new_value' => $change->new_value,
            'user_id' => (int) $change->user_id,
            'user_name' => $change->user?->name ?? 'Utilisateur #'.$change->user_id,
            'updated_at' => $change->updated_at?->toIso8601String(),
        ])->values()->all();
    }

    private function pairKey(string $sku, int $fieldId): string
    {
        return $sku.'|'.$fieldId;
    }
}
