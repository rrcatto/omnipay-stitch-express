<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

/** A webhook address registered, with its signing secret, which Stitch shows only now. */
class RegisterWebhookResponse extends Response
{
    public function getSecret(): ?string
    {
        $secret = $this->part()['secret'] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /** Whether the address was registered already (HTTP 409): its secret is in Stitch's dashboard. */
    public function isAlreadyRegistered(): bool
    {
        return $this->getCode() === '409';
    }
}
