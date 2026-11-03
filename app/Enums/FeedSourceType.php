<?php

namespace App\Enums;

enum FeedSourceType: string
{
    case Sftp = 'sftp';
    case Local = 'local';
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::Sftp => 'SFTP',
            self::Local => 'Fichier local',
            self::Url => 'URL distante',
        };
    }

    public function canWrite(): bool
    {
        return match ($this) {
            self::Sftp, self::Local => true,
            self::Url => false,
        };
    }
}
