<?php

namespace App\Auth;

use Illuminate\Support\Str;

class JwtTokenService
{
    /**
     * @return array{0: string, 1: int}
     */
    public function createAccessToken(
        string $userId,
        string $sessionId,
        ?string $companyId,
        ?string $deviceId = null,
        bool $isSuperAdmin = false,
    ): array {
        $expiresIn = (int) config('nexabiz.access_token_ttl_seconds');
        $now = time();
        $payload = [
            'sub' => $userId,
            'sid' => $sessionId,
            'typ' => 'access',
            'iat' => $now,
            'exp' => $now + $expiresIn,
            'iss' => (string) config('nexabiz.jwt_issuer'),
        ];
        if ($companyId !== null) {
            $payload['cid'] = $companyId;
        }
        if ($deviceId !== null) {
            $payload['did'] = $deviceId;
        }
        if ($isSuperAdmin) {
            $payload['sa'] = true;
        }

        return [$this->encode($payload), $expiresIn];
    }

    public function decodeAccessToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid token');
        }
        [$h64, $p64, $s64] = $parts;
        $header = json_decode($this->b64urlDecode($h64), true);
        if (! is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            throw new \InvalidArgumentException('Invalid token algorithm');
        }
        $expected = $this->sign($h64.'.'.$p64);
        if (! hash_equals($expected, $s64)) {
            throw new \InvalidArgumentException('Invalid token signature');
        }
        $payload = json_decode($this->b64urlDecode($p64), true);
        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Invalid token payload');
        }
        foreach (['exp', 'sub', 'sid', 'typ'] as $required) {
            if (! array_key_exists($required, $payload)) {
                throw new \InvalidArgumentException('Invalid token claims');
            }
        }
        if (($payload['iss'] ?? null) !== (string) config('nexabiz.jwt_issuer')) {
            throw new \InvalidArgumentException('Invalid token issuer');
        }
        if ((int) $payload['exp'] < time()) {
            throw new \InvalidArgumentException('Token expired');
        }

        return $payload;
    }

    public function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }

    public function generateRefreshToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private function encode(array $payload): string
    {
        $header = $this->b64urlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $body = $this->b64urlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = $this->sign($header.'.'.$body);

        return $header.'.'.$body.'.'.$sig;
    }

    private function sign(string $data): string
    {
        $raw = hash_hmac('sha256', $data, (string) config('nexabiz.jwt_secret'), true);

        return $this->b64urlEncode($raw);
    }

    private function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid token encoding');
        }

        return $decoded;
    }

    public static function newUuid(): string
    {
        return (string) Str::uuid();
    }
}
