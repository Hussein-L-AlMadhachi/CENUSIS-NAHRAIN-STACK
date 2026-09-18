<?php

declare(strict_types=1);

namespace Cenusis\Auth;

use RuntimeException;

/**
 * Pure-PHP HS256 JWT (encode/decode), no external dependency.
 *
 * Encoding mirrors jsonwebtoken's sign(): payload gets iat/exp,
 * issuer and audience fields. Decoding mirrors the TS verifier used
 * by this project: signature + expiry only (iss/aud not validated),
 * plus basic claim type checks done by the caller.
 */
final class Jwt
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(
        array $payload,
        string $secret,
        int $expiresIn = 0,
        ?string $issuer = null,
        ?string $audience = null
    ): string {
        $payload['iat'] = time();
        if ($expiresIn > 0) {
            $payload['exp'] = time() + $expiresIn;
        }
        if ($issuer !== null) {
            $payload['iss'] = $issuer;
        }
        if ($audience !== null) {
            $payload['aud'] = $audience;
        }

        $header = self::b64urlEncode((string)json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = self::b64urlEncode((string)json_encode($payload));
        $signature = self::b64urlEncode(hash_hmac('sha256', $header . '.' . $body, $secret, true));

        return $header . '.' . $body . '.' . $signature;
    }

    /**
     * Verify signature and expiry; return the payload array or null.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;

        $headerData = json_decode(self::b64urlDecode($header), true);
        if (!is_array($headerData) || ($headerData['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expected = self::b64urlEncode(hash_hmac('sha256', $header . '.' . $body, $secret, true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::b64urlDecode($body), true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && (!is_numeric($payload['exp']) || (int)$payload['exp'] < time())) {
            return null;
        }

        return $payload;
    }

    public static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }

    public static function secret(): string
    {
        $secret = getenv('JWT_SECRET');
        if ($secret === false || $secret === '') {
            throw new RuntimeException('JWT_SECRET is not configured');
        }
        return $secret;
    }
}
