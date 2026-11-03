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
}
