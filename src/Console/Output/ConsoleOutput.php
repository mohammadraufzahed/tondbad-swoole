<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Output;

class ConsoleOutput implements OutputInterface
{
    private int $verbosity = self::VERBOSITY_NORMAL;
    private bool $ansi;
    /** @var resource */
    private $stream;

    /**
     * @param resource|null $stream
     */
    public function __construct(int $verbosity = self::VERBOSITY_NORMAL, ?bool $ansi = null, $stream = STDOUT)
    {
        $this->verbosity = $verbosity;
        $this->stream = $stream ?? STDOUT;
        $this->ansi = $ansi ?? $this->supportsAnsi();
    }

    public function write(string $message, bool $newline = false): void
    {
        if ($this->isQuiet()) {
            return;
        }

        fwrite($this->stream, $message . ($newline ? PHP_EOL : ''));
    }

    public function writeln(string $message): void
    {
        $this->write($message, true);
    }

    public function newLine(int $count = 1): void
    {
        $this->write(str_repeat(PHP_EOL, $count), false);
    }

    public function isQuiet(): bool
    {
        return $this->verbosity <= self::VERBOSITY_QUIET;
    }

    public function isVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERBOSE;
    }

    public function isVeryVerbose(): bool
    {
        return $this->verbosity >= self::VERBOSITY_VERY_VERBOSE;
    }

    public function isDebug(): bool
    {
        return $this->verbosity >= self::VERBOSITY_DEBUG;
    }

    public function setVerbosity(int $level): void
    {
        $this->verbosity = $level;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }

    public function setAnsi(bool $ansi): void
    {
        $this->ansi = $ansi;
    }

    public function isAnsi(): bool
    {
        return $this->ansi;
    }

    public function success(string $message): void
    {
        $this->block($message, 'OK', 'green', 'white');
    }

    public function error(string $message): void
    {
        $this->block($message, 'ERROR', 'red', 'white');
    }

    public function warning(string $message): void
    {
        $this->block($message, 'WARNING', 'yellow', 'black');
    }

    public function info(string $message): void
    {
        $this->block($message, 'INFO', 'blue', 'white');
    }

    public function note(string $message): void
    {
        $this->block($message, 'NOTE', 'cyan', 'black');
    }

    public function caution(string $message): void
    {
        $this->block($message, 'CAUTION', 'yellow', 'black', true);
    }

    public function comment(string $message): void
    {
        $this->writeln($this->style($message, 'gray'));
    }

    public function title(string $message): void
    {
        $this->writeln('');
        $this->writeln($this->style($message, 'white', null, true));
        $this->writeln($this->style(str_repeat('=', min(60, strlen($message))), 'white', null, true));
    }

    public function section(string $message): void
    {
        $this->writeln('');
        $this->writeln($this->style($message, 'white', null, true));
    }

    public function listing(array $items): void
    {
        foreach ($items as $item) {
            $this->writeln(' * ' . $item);
        }
    }

    public function table(array $headers, array $rows): void
    {
        $widths = [];

        foreach ($headers as $i => $header) {
            $widths[$i] = strlen((string) $header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        $this->writeTableRow($headers, $widths, true);
        $this->writeln(str_repeat('-', array_sum($widths) + count($widths) * 3 + 1));

        foreach ($rows as $row) {
            $this->writeTableRow($row, $widths, false);
        }
    }

    public function ask(string $question, ?string $default = null): string
    {
        $prompt = $question . ($default !== null ? " [{$default}]: " : ': ');
        $this->write($prompt, false);

        $input = $this->readLine();

        return $input !== '' ? $input : ($default ?? '');
    }

    public function confirm(string $question, bool $default = true): bool
    {
        $suffix = $default ? ' [Y/n]: ' : ' [y/N]: ';
        $answer = $this->ask($question . $suffix, $default ? 'y' : 'n');

        return in_array(strtolower(trim($answer)), ['y', 'yes'], true);
    }

    public function choice(string $question, array $choices, ?string $default = null): string
    {
        $this->writeln($question);

        $keys = array_keys($choices);
        foreach ($choices as $key => $label) {
            $this->writeln("  [{$key}] {$label}");
        }

        $answer = $this->ask('Choose', $default);

        if (!in_array($answer, $keys, true)) {
            $this->error("Invalid choice: {$answer}");

            return $this->choice($question, $choices, $default);
        }

        return $answer;
    }

    public function progressBar(int $max): ProgressBar
    {
        return new ProgressBar($this, $max);
    }

    /**
     * @param array<int, mixed> $row
     * @param array<int, int> $widths
     */
    private function writeTableRow(array $row, array $widths, bool $isHeader): void
    {
        $line = '|';
        foreach ($widths as $i => $width) {
            $cell = (string) ($row[$i] ?? '');
            $line .= ' ' . str_pad($cell, $width) . ' |';
        }

        if ($isHeader) {
            $line = $this->style($line, 'white', null, true);
        }

        $this->writeln($line);
    }

    private function block(string $message, string $label, string $bg, string $fg, bool $bold = false): void
    {
        $prefix = $this->style(" {$label} ", $fg, $bg, $bold);
        $this->writeln($prefix . ' ' . $message);
    }

    private function style(string $message, string $color, ?string $bg = null, bool $bold = false): string
    {
        if (!$this->ansi) {
            return $message;
        }

        $codes = [];

        if ($bold) {
            $codes[] = '1';
        }

        if ($color !== null) {
            $codes[] = $this->foregroundCode($color);
        }

        if ($bg !== null) {
            $codes[] = $this->backgroundCode($bg);
        }

        return "\033[" . implode(';', $codes) . "m" . $message . "\033[0m";
    }

    private function foregroundCode(string $color): string
    {
        return match ($color) {
            'black' => '30', 'red' => '31', 'green' => '32', 'yellow' => '33',
            'blue' => '34', 'magenta' => '35', 'cyan' => '36', 'white' => '37',
            'gray' => '90', 'lightgray' => '37', 'default' => '39',
            default => '39',
        };
    }

    private function backgroundCode(string $color): string
    {
        return match ($color) {
            'black' => '40', 'red' => '41', 'green' => '42', 'yellow' => '43',
            'blue' => '44', 'magenta' => '45', 'cyan' => '46', 'white' => '47',
            default => '49',
        };
    }

    private function readLine(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $line = trim((string) fgets(STDIN));

            return $line;
        }

        $line = fgets(STDIN);

        return $line === false ? '' : trim($line);
    }

    private function supportsAnsi(): bool
    {
        $noColor = getenv('NO_COLOR');

        if ($noColor !== false && $noColor !== '') {
            return false;
        }

        $meta = stream_get_meta_data($this->stream);

        if (($meta['stream_type'] ?? '') === 'STDIO' && function_exists('posix_isatty') && @posix_isatty($this->stream)) {
            return true;
        }

        return PHP_OS_FAMILY !== 'Windows';
    }
}
