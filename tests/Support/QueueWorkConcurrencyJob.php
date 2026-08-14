<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Support;

use TondbadSwoole\Queue\Jobs\Job;

class QueueWorkConcurrencyJob extends Job
{
    public function __construct(public readonly int $index) {}

    public function handle(): void
    {
        $marker = sys_get_temp_dir() . '/tondbad_qw_' . $this->getJobId() . '.marker';
        file_put_contents($marker, (string) $this->index, LOCK_EX);
    }
}
