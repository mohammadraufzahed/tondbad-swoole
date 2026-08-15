<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

/**
 * Statistical helpers for benchmark samples.
 */
final class Statistics
{
    /**
     * @param list<float> $samples
     * @return array<string, float|int>
     */
    public static function analyze(array $samples): array
    {
        $n = count($samples);

        if ($n === 0) {
            return [
                'min' => 0.0,
                'max' => 0.0,
                'mean' => 0.0,
                'median' => 0.0,
                'stddev' => 0.0,
                'p95' => 0.0,
                'ci95Lower' => 0.0,
                'ci95Upper' => 0.0,
                'opsPerSecond' => 0.0,
                'outliers' => 0,
            ];
        }

        sort($samples);
        $min = $samples[0];
        $max = $samples[$n - 1];
        $sum = array_sum($samples);
        $mean = $sum / $n;

        $median = self::percentile($samples, 0.5);
        $p95 = self::percentile($samples, 0.95);

        $variance = 0.0;
        foreach ($samples as $value) {
            $diff = $value - $mean;
            $variance += $diff * $diff;
        }
        $stddev = $n > 1 ? sqrt($variance / ($n - 1)) : 0.0;

        // 95% confidence interval using the t-value approximation (1.96 for large N).
        $ciHalf = 1.96 * $stddev / sqrt($n);
        $ci95Lower = max(0.0, $mean - $ciHalf);
        $ci95Upper = $mean + $ciHalf;

        $opsPerSecond = $mean > 0 ? 1_000_000_000.0 / $mean : 0.0;

        $outliers = self::countOutliers($samples);

        return [
            'min' => $min,
            'max' => $max,
            'mean' => $mean,
            'median' => $median,
            'stddev' => $stddev,
            'p95' => $p95,
            'ci95Lower' => $ci95Lower,
            'ci95Upper' => $ci95Upper,
            'opsPerSecond' => $opsPerSecond,
            'outliers' => $outliers,
        ];
    }

    /**
     * @param list<float> $sorted
     */
    public static function percentile(array $sorted, float $p): float
    {
        $n = count($sorted);

        if ($n === 0) {
            return 0.0;
        }

        if ($n === 1) {
            return $sorted[0];
        }

        $index = (int) floor($p * ($n - 1));

        return $sorted[max(0, min($n - 1, $index))];
    }

    /**
     * @param list<float> $sorted
     */
    public static function countOutliers(array $sorted): int
    {
        $n = count($sorted);

        if ($n < 4) {
            return 0;
        }

        $q1Index = (int) floor(0.25 * ($n - 1));
        $q3Index = (int) floor(0.75 * ($n - 1));
        $q1 = $sorted[$q1Index];
        $q3 = $sorted[$q3Index];
        $iqr = $q3 - $q1;
        $lower = $q1 - 1.5 * $iqr;
        $upper = $q3 + 1.5 * $iqr;
        $outliers = 0;

        foreach ($sorted as $value) {
            if ($value < $lower || $value > $upper) {
                $outliers++;
            }
        }

        return $outliers;
    }
}
