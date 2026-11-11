<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress;

/**
 * Stitch Express signs each webhook delivery through Svix (https://docs.svix.com/receiving/verifying-payloads/how-manual):
 * the headers `svix-id`, `svix-timestamp` (Unix seconds) and `svix-signature` (one or more `v1,<base64>`, separated by
 * spaces), where each signature is the base64 HMAC-SHA256 of `{svix-id}.{svix-timestamp}.{raw body}` keyed by the
 * secret's bytes (the base64 after `whsec_`). A delivery is genuine when one signature matches, compared in constant
 * time, and its timestamp is within five minutes of now, so an old delivery cannot be replayed.
 */
final class WebhookSignature
{
    public const TOLERANCE = 300;

    private function __construct()
    {
    }

    public static function valid(string $secret, string $id, string $timestamp, string $signatures, string $body, ?int $now = null, int $tolerance = self::TOLERANCE): bool
    {
        if ($secret === '' || $id === '' || $signatures === '' || preg_match('/^\d+$/', $timestamp) !== 1) {
            return false;
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            return false;
        }
        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false || $key === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $body, $key, true));
        foreach (preg_split('/\s+/', trim($signatures)) ?: [] as $signature) {
            [$version, $value] = array_pad(explode(',', $signature, 2), 2, '');
            if ($version === 'v1' && hash_equals($expected, $value)) {
                return true;
            }
        }

        return false;
    }

    /** A signature header for a body, as Svix writes it: what a merchant's own tests send. */
    public static function sign(string $secret, string $id, string $timestamp, string $body): string
    {
        $key = (string) base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);

        return 'v1,' . base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $body, $key, true));
    }
}
