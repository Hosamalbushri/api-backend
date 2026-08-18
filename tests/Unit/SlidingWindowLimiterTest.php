<?php

namespace Tests\Unit;

use App\Support\SlidingWindowLimiter;
use Tests\TestCase;

class SlidingWindowLimiterTest extends TestCase
{
    public function test_sliding_window_allows_then_blocks(): void
    {
        $limiter = new SlidingWindowLimiter(2, 60);
        $this->assertTrue($limiter->allow('ip')[0]);
        $this->assertTrue($limiter->allow('ip')[0]);
        [$allowed, $retry] = $limiter->allow('ip');
        $this->assertFalse($allowed);
        $this->assertGreaterThanOrEqual(1, $retry);
        $this->assertTrue($limiter->allow('other')[0]);
    }

    public function test_sliding_window_disabled_when_max_zero(): void
    {
        $limiter = new SlidingWindowLimiter(0);
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($limiter->allow('ip')[0]);
        }
    }
}
