<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Locks;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Scheduling\Contracts\LockProvider;

class FileLockProvider implements LockProvider
{
    /**
     * @var array<string, resource>
     */
    private array $handles = [];

    public function __construct(
        private readonly string $basePath,
        private readonly ?Config $config = null,
    ) {
    }

    public function acquire(string $key, int $timeoutMs = 0): bool
    {
        $file = $this->lockFile($key);
        $this->ensureDirectory(dirname($file));

        $handle = @fopen($file, 'c');

        if ($handle === false) {
            return false;
        }

        $operation = $timeoutMs > 0 ? LOCK_EX : LOCK_EX | LOCK_NB;
        $wouldBlock = 0;

        if ($timeoutMs > 0) {
            $timeoutS = (int) ceil($timeoutMs / 1000.0);

            $start = microtime(true);

            while (!flock($handle, $operation, $wouldBlock)) {
                if ((microtime(true) - $start) * 1000 >= $timeoutMs) {
                    fclose($handle);

                    return false;
                }

                usleep(10000);
            }
        } else {
            if (!flock($handle, $operation, $wouldBlock)) {
                fclose($handle);

                return false;
            }
        }

        $this->handles[$key] = $handle;

        return true;
    }

    public function release(string $key): void
    {
        if (!isset($this->handles[$key])) {
            return;
        }

        flock($this->handles[$key], LOCK_UN);
        fclose($this->handles[$key]);

        unset($this->handles[$key]);
    }

    private function lockFile(string $key): string
    {
        $frameworkDir = $this->config !== null
            ? $this->config->get('app.framework_cache_dir', $this->basePath . '/storage/framework')
            : $this->basePath . '/storage/framework';

        return $frameworkDir . '/schedule-' . md5($key) . '.lock';
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }
}
