<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Registers the merchant's webhook address (POST /api/v1/webhook), which Stitch sends each `payment.paid` event to. The
 * address must be public and on https. The reply carries the signing secret (whsec_...), shown this once: keep it as the
 * gateway's `webhookSecret`. Registering an address already registered is refused (HTTP 409); its secret is then only
 * renewed from Stitch's dashboard.
 */
class RegisterWebhookRequest extends AbstractRequest
{
    public function getUrl(): ?string
    {
        return $this->getParameter('url');
    }

    public function setUrl(?string $value): static
    {
        return $this->setParameter('url', $value);
    }

    /** @return array{url:string} */
    public function getData(): array
    {
        $this->validate('url');
        $url = (string) $this->getUrl();
        if (!str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidRequestException('A webhook address is a public https:// address.');
        }

        return ['url' => $url];
    }

    public function send(): RegisterWebhookResponse
    {
        return $this->sendData($this->getData());
    }

    /** @param array{url:string} $data */
    public function sendData($data): RegisterWebhookResponse
    {
        [$status, $reply] = $this->call('POST', '/api/v1/webhook', $data);

        return $this->response = new RegisterWebhookResponse($this, $reply, $status);
    }
}
