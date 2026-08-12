<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Queue\QueueManager;

class QueueStatusCommand extends Command
{
    public function getName(): string
    {
        return 'queue:status';
    }

    public function getDescription(): string
    {
        return 'Show queue metrics by status.';
    }

    public function run(array $args): int
    {
        $options = $this->parseOptions($args);
        $connectionName = $options['connection'] ?? null;
        $queue = $options['queue'] ?? 'default';

        $app = app();

        if ($app === null) {
            fwrite(STDERR, "Application not booted.\n");

            return 1;
        }

        $queueManager = $app->container->make(QueueManager::class);
        $connection = $queueManager->connection($connectionName);
        $metrics = $connection->getMetrics($queue);

        fwrite(STDOUT, "Metrics for queue: {$queue}\n");
        fwrite(STDOUT, str_repeat('-', 30) . "\n");

        foreach ($metrics as $status => $count) {
            fwrite(STDOUT, sprintf("%-15s %d\n", $status, $count));
        }

        return 0;
    }

    private function parseOptions(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $option = substr($arg, 2);
                [$key, $value] = array_pad(explode('=', $option, 2), 2, true);
                $options[$key] = $value === true ? true : $value;
            }
        }

        return $options;
    }
}
