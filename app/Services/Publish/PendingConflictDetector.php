<?php

namespace App\Services\Publish;

use App\Models\FeedFile;
use App\Models\PendingPriceChange;
use App\Models\User;

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

        $myKeys = $mine
            ->map(fn (PendingPriceChange $change): string => $this->pairKey($change->sku, $change->csv_column))
            ->all();

        $mineByKey = $mine->keyBy(
            fn (PendingPriceChange $change): string => $this->pairKey($change->sku, $change->csv_column)
        );

        $others = PendingPriceChange::query()
            ->where('feed_file_id', $feed->id)
            ->where('user_id', '!=', $user->id)
            ->where(function ($query) use ($mine): void {
                foreach ($mine as $change) {
                    $query->orWhere(function ($inner) use ($change): void {
                        $inner->where('sku', $change->sku)
                            ->where('csv_column', $change->csv_column);
                    });
                }
            })
            ->with(['user:id,name'])
            ->get();

        $conflicts = [];

        foreach ($others as $other) {
            $key = $this->pairKey($other->sku, $other->csv_column);
            if (! in_array($key, $myKeys, true)) {
                continue;
            }

            $mineChange = $mineByKey->get($key);

            $conflicts[] = [
                'sku' => $other->sku,
                'csv_column' => $other->csv_column,
                'other_user_id' => (int) $other->user_id,
                'other_user_name' => $other->user?->name ?? 'Utilisateur #'.$other->user_id,
                'other_new_price' => (string) $other->new_price,
                'my_new_price' => (string) ($mineChange?->new_price ?? ''),
            ];
        }

        return $conflicts;
    }

    private function pairKey(string $sku, string $csvColumn): string
    {
        return $sku.'|'.$csvColumn;
    }
}
