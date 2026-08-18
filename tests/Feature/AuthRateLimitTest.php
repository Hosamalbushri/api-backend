<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    public function test_middleware_returns_429(): void
    {
        config(['nexabiz.auth_rate_limit_per_minute' => 1]);
        Cache::flush();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'a@example.com',
            'password' => 'x',
        ]);
        $second = $this->postJson('/api/v1/auth/login', [
            'email' => 'a@example.com',
            'password' => 'x',
        ]);
        $second->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')
            ->assertHeader('Retry-After');
    }
}
