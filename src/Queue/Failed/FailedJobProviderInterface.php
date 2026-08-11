<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Failed;

use Throwable;
use TondbadSwoole\Queue\Jobs\Job;

interface FailedJobProviderInterface
{
    public function log(Job $job, Throwable $exception): mixed;
}
