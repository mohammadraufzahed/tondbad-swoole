<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Application;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Argument;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('completion', 'Generate shell completion script.')]
class CompletionCommand extends Command
{
    #[Argument('shell', mode: InputArgument::OPTIONAL, description: 'Shell to generate completion for', allowed: ['bash', 'zsh'], default: 'bash')]
    public string $shell = 'bash';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = app();

        if ($app === null) {
            $output->error('Application not booted.');

            return 1;
        }

        $console = $app->container->make(Application::class);
        $names = $console->getCommandNames();
        sort($names);
        $commands = implode(' ', $names);

        if ($this->shell === 'zsh') {
            $output->writeln('#compdef tondbad');
            $output->writeln('_tondbad_commands=(' . $commands . ')');
            $output->writeln('compadd -a _tondbad_commands');

            return 0;
        }

        $output->writeln('_tondbad_completion() {');
        $output->writeln('    local cur=${COMP_WORDS[COMP_CWORD]}');
        $output->writeln('    COMPREPLY=( $(compgen -W "' . $commands . '" -- $cur) )');
        $output->writeln('}');
        $output->writeln('complete -F _tondbad_completion tondbad');

        return 0;
    }
}
