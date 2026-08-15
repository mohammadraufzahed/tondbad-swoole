<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands\Auth;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Commands\Command;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Database\DatabaseManager;

#[AsCommand('auth:clear-sessions', 'Revoke all active sessions and refresh tokens.')]
class ClearSessionsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $databaseManager = app()?->container?->make(DatabaseManager::class);

        if ($databaseManager !== null) {
            $databaseManager->table('sessions')->delete();
            $databaseManager->table('refresh_tokens')->update(['revoked' => true]);
        }

        $output->success('All sessions and refresh tokens cleared.');

        return 0;
    }
}
