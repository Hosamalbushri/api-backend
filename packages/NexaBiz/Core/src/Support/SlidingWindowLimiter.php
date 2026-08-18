<?php

namespace NexaBiz\Core\Support;

use Illuminate\Support\Facades\Cache;

class SlidingWindowLimiter
{
    /**
     * In-process (plus cache-backed) sliding window limiter.
     * max_requests <= 0 disables the limiter, matching Python.
     */
    public function __construct(
        public int $maxRequests,
        public float $windowSeconds = 60.0,
    ) {}

    /**
     * @return array{0: bool, 1: int} [allowed, retry_after_seconds]
     */
    public function allow(string $key, ?int $maxRequests = null): array
    {
        $limit = $maxRequests ?? $this->maxRequests;
        if ($limit <= 0) {
            return [true, 0];
        }

        $now = microtime(true);
        $cutoff = $now - $this->windowSeconds;
        $cacheKey = 'nexabiz:rate:'.$key;
        $bucket = Cache::get($cacheKey, []);
        $bucket = array_values(array_filter($bucket, fn ($t) => $t > $cutoff));

        if (count($bucket) >= $limit) {
            $oldest = $bucket[0];
            $retry = (int) ($this->windowSeconds - ($now - $oldest)) + 1;

            return [false, max($retry, 1)];
        }

        $bucket[] = $now;
        Cache::put($cacheKey, $bucket, (int) ceil($this->windowSeconds) + 1);

        return [true, 0];
    }

    public function reset(): void
    {
        // Intentionally no-op for cache store; tests use a fresh array store.
    }
}
