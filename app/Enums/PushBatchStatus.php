<?php

namespace App\Enums;

enum PushBatchStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Queued => 'En file',
            self::Running => 'En cours',
            self::Succeeded => 'Réussi',
            self::Failed => 'Échec',
            self::Blocked => 'Bloqué',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Succeeded => 'ok',
            self::Failed, self::Blocked => 'danger',
            self::Running, self::Queued => 'warn',
            self::Pending => 'muted',
        };
    }
}
