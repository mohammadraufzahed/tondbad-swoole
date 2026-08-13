<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Support;

use OpenSwoole\Coroutine;
use OpenSwoole\Runtime;
use Throwable;

class SwooleCoroutineTestCase
{
    /**
     * Run a closure inside an OpenSwoole coroutine context with all hooks enabled.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     *
     * @throws Throwable
     */
    public static function run(callable $callback, int $flags = Runtime::HOOK_ALL): mixed
    {
        Runtime::enableCoroutine($flags);

        $result = null;
        $error = null;

        Coroutine::run(function () use ($callback, &$result, &$error): void {
            try {
                $result = $callback();
            } catch (Throwable $e) {
                $error = $e;
            }
        });

        if ($error !== null) {
            throw $error;
        }

        return $result;
    }
}
