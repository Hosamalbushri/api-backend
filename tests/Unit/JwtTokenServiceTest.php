<?php

namespace Tests\Unit;

use App\Auth\JwtTokenService;
use Tests\TestCase;

class JwtTokenServiceTest extends TestCase
{
    public function test_round_trip_access_token_claims(): void
    {
        $jwt = new JwtTokenService;
        [$token] = $jwt->createAccessToken(
            userId: '00000000-0000-4000-8000-000000000002',
            sessionId: '00000000-0000-4000-8000-0000000000aa',
            companyId: '00000000-0000-4000-8000-000000000001',
            deviceId: '00000000-0000-4000-8000-0000000000a1',
            isSuperAdmin: true,
        );
        $payload = $jwt->decodeAccessToken($token);
        $this->assertSame('access', $payload['typ']);
        $this->assertSame('00000000-0000-4000-8000-000000000002', $payload['sub']);
        $this->assertSame('00000000-0000-4000-8000-0000000000aa', $payload['sid']);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $payload['cid']);
        $this->assertSame('00000000-0000-4000-8000-0000000000a1', $payload['did']);
        $this->assertTrue($payload['sa']);
        $this->assertSame(config('nexabiz.jwt_issuer'), $payload['iss']);
    }

    public function test_refresh_token_hash_is_sha256_hex(): void
    {
        $jwt = new JwtTokenService;
        $raw = $jwt->generateRefreshToken();
        $this->assertSame(hash('sha256', $raw), $jwt->hashToken($raw));
        $this->assertSame(64, strlen($jwt->hashToken($raw)));
    }
}
