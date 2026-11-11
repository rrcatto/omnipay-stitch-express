<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress;

/**
 * Access tokens held for their life within one PHP process, so a run of calls asks Stitch for one token rather than one
 * a call (the token endpoint allows 120 requests a minute). Each is kept by a hash of the credentials, scope and
 * address it was issued for, never by the secret itself, and dropped a minute before its expiry, which is read from
 * the token (a JWT's exp) or else taken as Stitch's 15 minutes.
 */
final class AccessTokens
{
    /** @var array<string,array{token:string,until:int}> */
    private static array $held = [];

    private function __construct()
    {
    }

    public static function key(string $clientId, string $clientSecret, string $scope, string $endpoint): string
    {
        return hash('sha256', $clientId . "\0" . $clientSecret . "\0" . $scope . "\0" . $endpoint);
    }

    public static function held(string $key): ?string
    {
        $held = self::$held[$key] ?? null;
        if ($held === null || $held['until'] <= time()) {
            unset(self::$held[$key]);

            return null;
        }

        return $held['token'];
    }

    public static function hold(string $key, string $token): void
    {
        self::$held[$key] = ['token' => $token, 'until' => self::expiry($token) - 60];
    }

    /** Forgets every held token (a test, or a merchant that has changed its secret). */
    public static function forget(): void
    {
        self::$held = [];
    }

    private static function expiry(string $token): int
    {
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
            if (is_array($claims) && is_int($claims['exp'] ?? null) && $claims['exp'] > time()) {
                return $claims['exp'];
            }
        }

        return time() + 15 * 60;
    }
}
