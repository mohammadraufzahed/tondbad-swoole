<?php

declare(strict_types=1);

namespace TondbadSwoole\Contracts\Cache;

class CacheStats implements \JsonSerializable
{
    public int $hitCount = 0;
    public int $missCount = 0;
    public int $l1HitCount = 0;
    public int $l2HitCount = 0;
    public int $loadCount = 0;
    public int $loadFailureCount = 0;
    public float $loadTime = 0.0;
    public int $refreshCount = 0;
    public int $refreshFailureCount = 0;
    public int $evictionCount = 0;
    public int $evictionWeight = 0;

    public function recordHit(bool $fromL1 = true): void
    {
        $this->hitCount++;

        if ($fromL1) {
            $this->l1HitCount++;
        } else {
            $this->l2HitCount++;
        }
    }

    public function recordMiss(): void
    {
        $this->missCount++;
    }

    public function recordLoad(float $duration, bool $success = true): void
    {
        $this->loadCount++;
        $this->loadTime += $duration;

        if (!$success) {
            $this->loadFailureCount++;
        }
    }

    public function recordRefresh(bool $success = true): void
    {
        $this->refreshCount++;

        if (!$success) {
            $this->refreshFailureCount++;
        }
    }

    public function recordEviction(int $weight = 1): void
    {
        $this->evictionCount++;
        $this->evictionWeight += $weight;
    }

    public function totalRequests(): int
    {
        return $this->hitCount + $this->missCount;
    }

    public function hitRate(): float
    {
        $total = $this->totalRequests();

        return $total === 0 ? 0.0 : round($this->hitCount / $total, 4);
    }

    public function l1HitRate(): float
    {
        $total = $this->totalRequests();

        return $total === 0 ? 0.0 : round($this->l1HitCount / $total, 4);
    }

    public function l2HitRate(): float
    {
        $total = $this->totalRequests();

        return $total === 0 ? 0.0 : round($this->l2HitCount / $total, 4);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'hitCount' => $this->hitCount,
            'missCount' => $this->missCount,
            'l1HitCount' => $this->l1HitCount,
            'l2HitCount' => $this->l2HitCount,
            'loadCount' => $this->loadCount,
            'loadFailureCount' => $this->loadFailureCount,
            'loadTime' => $this->loadTime,
            'refreshCount' => $this->refreshCount,
            'refreshFailureCount' => $this->refreshFailureCount,
            'evictionCount' => $this->evictionCount,
            'evictionWeight' => $this->evictionWeight,
            'hitRate' => $this->hitRate(),
            'l1HitRate' => $this->l1HitRate(),
            'l2HitRate' => $this->l2HitRate(),
        ];
    }
}
