<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Jobs;

enum JobStatus: string
{
    case Waiting = 'waiting';
    case Active = 'active';
    case Delayed = 'delayed';
    case Completed = 'completed';
    case Failed = 'failed';
    case WaitingChildren = 'waiting_children';
}
