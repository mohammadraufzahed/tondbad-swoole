<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands\Auth;

use OpenSwoole\Coroutine;
use OpenSwoole\Runtime;
use TondbadSwoole\Console\Commands\Command;
use TondbadSwoole\Database\DatabaseManager;

class ClearSessionsCommand extends Command
{
    public function getName(): string
    {
        return 'auth:clear-sessions';
    }

    public function getDescription(): string
    {
        return 'Revoke all active sessions and refresh tokens.';
    }

    public function run(array $args): int
    {
        $this->runInCoroutine(function (): void {
            $databaseManager = app()?->container?->make(DatabaseManager::class);

            if ($databaseManager !== null) {
                $databaseManager->table('sessions')->delete();
                $databaseManager->table('refresh_tokens')->update(['revoked' => true]);
            }
        });

        fwrite(STDOUT, "All sessions and refresh tokens cleared.\n");

        return 0;
    }

    private function runInCoroutine(callable $callback): mixed
    {
        if (Coroutine::getCid() !== -1) {
            return $callback();
        }

        Runtime::enableCoroutine(SWOOLE_HOOK_TCP);

        $result = null;
        Coroutine::run(function () use ($callback, &$result): void {
            $result = $callback();
        });

        return $result;
    }
}
