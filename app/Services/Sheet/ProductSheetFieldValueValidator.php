<?php

namespace App\Services\Sheet;

use App\Enums\ProductSheetFieldType;
use App\Models\ProductSheetField;
use InvalidArgumentException;

final class ProductSheetFieldValueValidator
{
    public function normalize(ProductSheetField $field, mixed $raw): string
    {
        $value = is_string($raw) ? $raw : (string) ($raw ?? '');

        return match ($field->type) {
            ProductSheetFieldType::TextLine => $this->normalizeLineText($field, $value),
            ProductSheetFieldType::TextPlain => $this->normalizePlainText($field, $value),
            ProductSheetFieldType::TextHtml => $this->normalizeHtmlText($field, $value),
            ProductSheetFieldType::HttpLink => $this->normalizeHttpLink($value),
            ProductSheetFieldType::Images => $this->normalizeImages($field, $value),
        };
    }

    private function normalizeLineText(ProductSheetField $field, string $value): string
    {
        $line = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        $this->assertMaxLength($field, $line);

        return $line;
    }

    private function normalizePlainText(ProductSheetField $field, string $value): string
    {
        $plain = trim(strip_tags($value));
        $this->assertMaxLength($field, $plain);

        return $plain;
    }

    private function normalizeHtmlText(ProductSheetField $field, string $value): string
    {
        $html = trim($value);
        $this->assertMaxLength($field, $html);

        return $html;
    }

    private function normalizeHttpLink(string $value): string
    {
        $url = trim($value);
        if ($url === '') {
            return '';
        }

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
            throw new InvalidArgumentException('Lien HTTP(S) invalide.');
        }

        return $url;
    }

    private function normalizeImages(ProductSheetField $field, string $value): string
    {
        $parts = preg_split('/\s*[|;,]\s*/', trim($value)) ?: [];
        $urls = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            if (! filter_var($part, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $part)) {
                throw new InvalidArgumentException('URL image invalide : '.$part);
            }
            if (! in_array($part, $urls, true)) {
                $urls[] = $part;
            }
        }

        if (count($urls) > $field->maxImages()) {
            throw new InvalidArgumentException('Trop d’images (max '.$field->maxImages().').');
        }

        return implode('|', $urls);
    }

    private function assertMaxLength(ProductSheetField $field, string $value): void
    {
        if ($field->max_length === null) {
            return;
        }

        if (mb_strlen($value) > $field->max_length) {
            throw new InvalidArgumentException(
                "Le champ « {$field->label} » dépasse {$field->max_length} caractères."
            );
        }
    }
}
