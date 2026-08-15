<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Output;

class ProgressBar
{
    private int $current = 0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly int $max,
    ) {
    }

    public function start(int $max = 0): void
    {
        if ($max > 0) {
            $this->max = $max;
        }

        $this->current = 0;
        $this->display();
    }

    public function advance(int $step = 1): void
    {
        $this->current += $step;

        if ($this->current > $this->max) {
            $this->current = $this->max;
        }

        $this->display();
    }

    public function finish(): void
    {
        $this->current = $this->max;
        $this->display();
        $this->output->writeln('');
    }

    private function display(): void
    {
        if ($this->output->isQuiet()) {
            return;
        }

        $percent = $this->max > 0 ? (int) round(($this->current / $this->max) * 100) : 0;
        $width = 30;
        $filled = (int) round(($percent / 100) * $width);
        $bar = '[' . str_repeat('=', $filled) . str_repeat('.', $width - $filled) . ']';

        $this->output->write("\r{$bar} {$percent}% {$this->current}/{$this->max}");
    }
}
