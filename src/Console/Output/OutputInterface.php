<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Output;

interface OutputInterface
{
    public const VERBOSITY_QUIET = 16;
    public const VERBOSITY_NORMAL = 32;
    public const VERBOSITY_VERBOSE = 64;
    public const VERBOSITY_VERY_VERBOSE = 128;
    public const VERBOSITY_DEBUG = 256;

    public function write(string $message, bool $newline = false): void;

    public function writeln(string $message): void;

    public function newLine(int $count = 1): void;

    public function isQuiet(): bool;

    public function isVerbose(): bool;

    public function isVeryVerbose(): bool;

    public function isDebug(): bool;

    public function setVerbosity(int $level): void;

    public function getVerbosity(): int;

    public function setAnsi(bool $ansi): void;

    public function isAnsi(): bool;

    public function success(string $message): void;

    public function error(string $message): void;

    public function warning(string $message): void;

    public function info(string $message): void;

    public function note(string $message): void;

    public function caution(string $message): void;

    public function comment(string $message): void;

    public function title(string $message): void;

    public function section(string $message): void;

    public function listing(array $items): void;

    public function table(array $headers, array $rows): void;

    public function ask(string $question, ?string $default = null): string;

    public function confirm(string $question, bool $default = true): bool;

    /**
     * @param array<string|int, string> $choices
     */
    public function choice(string $question, array $choices, ?string $default = null): string;

    /**
     * @param int<0, max> $max
     */
    public function progressBar(int $max): ProgressBar;
}
