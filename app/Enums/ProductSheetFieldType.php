<?php

namespace App\Enums;

enum ProductSheetFieldType: string
{
    case TextLine = 'text_line';
    case TextPlain = 'text_plain';
    case TextHtml = 'text_html';
    case HttpLink = 'http_link';
    case Images = 'images';

    public function label(): string
    {
        return match ($this) {
            self::TextLine => 'Ligne (input)',
            self::TextPlain => 'Texte libre',
            self::TextHtml => 'Texte HTML',
            self::HttpLink => 'Lien HTTP(S)',
            self::Images => 'Images (URLs)',
        };
    }

    public function allowsMaxLength(): bool
    {
        return match ($this) {
            self::TextLine, self::TextPlain, self::TextHtml => true,
            default => false,
        };
    }
}
