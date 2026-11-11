<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Tests;

use Omnipay\StitchExpress\WebhookSignature;
use PHPUnit\Framework\TestCase;

/** Svix's signature scheme, checked against the example in Svix's own guide to verifying deliveries by hand. */
final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';
    private const ID = 'msg_p5jXN8AQM9LWM0D4loKWxJek';
    private const TIMESTAMP = '1614265330';
    private const BODY = '{"test": 2432232314}';
    private const SIGNATURE = 'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=';

    public function testSvixsOwnExampleIsGenuine(): void
    {
        self::assertSame(self::SIGNATURE, WebhookSignature::sign(self::SECRET, self::ID, self::TIMESTAMP, self::BODY));
        self::assertTrue(WebhookSignature::valid(self::SECRET, self::ID, self::TIMESTAMP, self::SIGNATURE, self::BODY, (int) self::TIMESTAMP));
    }

    public function testOneMatchingSignatureAmongSeveralIsEnough(): void
    {
        self::assertTrue(WebhookSignature::valid(self::SECRET, self::ID, self::TIMESTAMP, 'v1,b3RoZXI= v2,' . substr(self::SIGNATURE, 3) . ' ' . self::SIGNATURE, self::BODY, (int) self::TIMESTAMP));
        self::assertFalse(WebhookSignature::valid(self::SECRET, self::ID, self::TIMESTAMP, 'v2,' . substr(self::SIGNATURE, 3), self::BODY, (int) self::TIMESTAMP), 'Only v1 signatures count.');
    }

    public function testATimestampMoreThanFiveMinutesAwayIsRefused(): void
    {
        self::assertTrue(WebhookSignature::valid(self::SECRET, self::ID, self::TIMESTAMP, self::SIGNATURE, self::BODY, (int) self::TIMESTAMP + 300));
        self::assertFalse(WebhookSignature::valid(self::SECRET, self::ID, self::TIMESTAMP, self::SIGNATURE, self::BODY, (int) self::TIMESTAMP + 301));
        self::assertFalse(WebhookSignature::valid(self::SECRET, self::ID, self::TIMESTAMP, self::SIGNATURE, self::BODY, (int) self::TIMESTAMP - 301));
    }

    public function testAnythingMalformedIsRefused(): void
    {
        foreach ([
            ['', self::ID, self::TIMESTAMP, self::SIGNATURE, self::BODY],
            ['whsec_***not base64***', self::ID, self::TIMESTAMP, self::SIGNATURE, self::BODY],
            [self::SECRET, '', self::TIMESTAMP, self::SIGNATURE, self::BODY],
            [self::SECRET, self::ID, 'soon', self::SIGNATURE, self::BODY],
            [self::SECRET, self::ID, self::TIMESTAMP, '', self::BODY],
            [self::SECRET, self::ID, self::TIMESTAMP, self::SIGNATURE, self::BODY . ' '],
        ] as $case) {
            self::assertFalse(WebhookSignature::valid(...[...$case, (int) self::TIMESTAMP]), (string) json_encode($case));
        }
    }

    public function testASecretWithoutItsPrefixIsTheSameKey(): void
    {
        self::assertTrue(WebhookSignature::valid(substr(self::SECRET, 6), self::ID, self::TIMESTAMP, self::SIGNATURE, self::BODY, (int) self::TIMESTAMP));
    }
}
