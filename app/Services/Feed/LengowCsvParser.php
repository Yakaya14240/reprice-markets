<?php

namespace App\Services\Feed;

final class LengowCsvParser
{
    /**
     * @param  list<list<string|null>>  $rows
     * @return array{headers: list<string>, products: list<array<string, string|null>>}
     */
    public function parse(array $rows): array
    {
        if ($rows === []) {
            return ['headers' => [], 'products' => []];
        }

        $headers = array_map(static fn ($h) => (string) $h, $rows[0]);
        $products = [];

        foreach (array_slice($rows, 1) as $row) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = $row[$index] ?? null;
            }
            $products[] = $assoc;
        }

        return [
            'headers' => $headers,
            'products' => $products,
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string|null>>  $products
     * @return list<list<string|null>>
     */
    public function toRows(array $headers, array $products): array
    {
        $rows = [$headers];

        foreach ($products as $product) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = $product[$header] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, string|null>  $product
     */
    public function extractSku(array $product): ?string
    {
        foreach (['SKU', 'Identifiant_unique', 'sku', 'offer-sku'] as $key) {
            if (! empty($product[$key])) {
                return (string) $product[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $product
     */
    public function extractEan(array $product): ?string
    {
        foreach (['EAN', 'ean', 'GTIN', 'gtin'] as $key) {
            if (! empty($product[$key])) {
                return (string) $product[$key];
            }
        }

        return null;
    }
}
