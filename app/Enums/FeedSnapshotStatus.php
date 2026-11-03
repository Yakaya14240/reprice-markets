<?php

namespace App\Enums;

enum FeedSnapshotStatus: string
{
    case Idle = 'idle';
    case Loading = 'loading';
    case Ready = 'ready';
    case Failed = 'failed';
}
