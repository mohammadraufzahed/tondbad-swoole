<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use TondbadSwoole\Core\Container;
use TondbadSwoole\Scheduling\Contracts\Task;

class TaskRunner
{
    public function __construct(
        private readonly Container $container,
        private readonly string $basePath,
        private readonly ?ScheduleRegistry $registry = null,
    ) {
    }

    public function run(Task $task, ?string $outputPath = null): mixed
    {
        $obLevel = ob_get_level();

        if ($outputPath !== null) {
            ob_start();
        }

        try {
            $result = $task->execute($this->container, $this->basePath, $this->registry);

            if ($outputPath !== null) {
                $output = ob_get_clean();

                if ($output !== false && $output !== '') {
                    $this->ensureDirectory(dirname($outputPath));
                    file_put_contents($outputPath, $output, FILE_APPEND | LOCK_EX);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            if ($outputPath !== null) {
                while (ob_get_level() > $obLevel) {
                    ob_end_clean();
                }
            }

            throw $e;
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }
}
