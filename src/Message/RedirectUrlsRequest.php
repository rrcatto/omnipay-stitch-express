<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * The addresses Stitch may send a customer back to (up to five an account): listed, registered or removed. Stitch
 * compares a payment link's `redirect_url` with them exactly, after dropping its query string and one trailing slash,
 * so register each without either; `http://localhost` is accepted for testing.
 */
class RedirectUrlsRequest extends AbstractRequest
{
    public function getAction(): string
    {
        $action = $this->getParameter('action');

        return is_string($action) ? $action : 'list';
    }

    /** list, register or delete. */
    public function setAction(string $value): static
    {
        return $this->setParameter('action', $value);
    }

    public function getRedirectUrl(): ?string
    {
        return $this->getParameter('redirectUrl');
    }

    public function setRedirectUrl(?string $value): static
    {
        return $this->setParameter('redirectUrl', $value);
    }

    /** @return array{redirectUrl?:string} */
    public function getData(): array
    {
        $action = $this->getAction();
        if (!in_array($action, ['list', 'register', 'delete'], true)) {
            throw new InvalidRequestException('A redirect address is listed, registered or deleted.');
        }
        if ($action === 'list') {
            return [];
        }
        $this->validate('redirectUrl');
        $url = (string) $this->getRedirectUrl();
        if (preg_match('#^https://[^\s?]+$|^http://localhost(:\d+)?(/[^\s?]*)?$#', $url) !== 1) {
            throw new InvalidRequestException('A redirect address starts with https:// (or http://localhost for testing) and has no query string.');
        }

        // Stitch drops one trailing slash from the address a customer is sent back to before comparing, so one registered
        // with it would never match.
        if ($action === 'register' && str_ends_with($url, '/')) {
            $url = substr($url, 0, -1);
        }

        return ['redirectUrl' => $url];
    }

    public function send(): RedirectUrlsResponse
    {
        return $this->sendData($this->getData());
    }

    /** @param array{redirectUrl?:string} $data */
    public function sendData($data): RedirectUrlsResponse
    {
        [$status, $reply] = match ($this->getAction()) {
            'register' => $this->call('POST', '/api/v1/redirect-urls', $data),
            'delete' => $this->call('DELETE', '/api/v1/redirect-urls', $data),
            default => $this->call('GET', '/api/v1/redirect-urls'),
        };

        return $this->response = new RedirectUrlsResponse($this, $reply, $status);
    }
}
