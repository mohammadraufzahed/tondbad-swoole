<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark\Reporters;

use TondbadSwoole\Benchmark\BenchmarkResult;

final class JsonReporter implements Reporter
{
    public function report(array $results, ?string $output = null): void
    {
        $data = array_map(fn (BenchmarkResult $r) => $r->toArray(), $results);
        $json = json_encode($data, JSON_PRETTY_PRINT) . "\n";

        if ($output === null) {
            fwrite(STDOUT, $json);

            return;
        }

        file_put_contents($output, $json);
    }
}
