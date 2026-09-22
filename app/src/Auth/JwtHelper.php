<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use SilverStripe\Core\Environment;

/**
 * Issues and verifies short-lived JWT access tokens for the Vue app's API.
 * Not used for the CMS admin, which keeps SilverStripe's own session login.
 */
class JwtHelper
{
    private const ALGO = 'HS256';
    private const ACCESS_TOKEN_TTL = 900; // 15 minutes

    private static function secret(): string
    {
        $secret = Environment::getEnv('JWT_SECRET');
        if (!$secret) {
            throw new \RuntimeException('JWT_SECRET is not configured.');
        }
        return $secret;
    }

    public static function issueAccessToken(int $memberID): string
    {
        $now = time();
        $payload = [
            'sub' => $memberID,
            'iat' => $now,
            'exp' => $now + self::ACCESS_TOKEN_TTL,
            'jti' => bin2hex(random_bytes(8)),
        ];

        return JWT::encode($payload, self::secret(), self::ALGO);
    }

    /**
     * Returns the MemberID encoded in a valid, non-expired access token, or null.
     */
    public static function verifyAccessToken(string $token): ?int
    {
        try {
            $payload = JWT::decode($token, new Key(self::secret(), self::ALGO));
            return (int) $payload->sub;
        } catch (ExpiredException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function accessTokenTtl(): int
    {
        return self::ACCESS_TOKEN_TTL;
    }
}
