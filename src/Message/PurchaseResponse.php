<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;

/**
 * A payment link made: a redirect to Stitch's page, where the customer pays. Its transaction reference is the link's id,
 * which the merchant keeps to ask about the link later (`completePurchase()`, `fetchTransaction()`).
 */
class PurchaseResponse extends Response implements RedirectResponseInterface
{
    /** @param array<string,mixed> $data */
    public function __construct(RequestInterface $request, array $data, int $status, private readonly ?string $returnUrl = null)
    {
        parent::__construct($request, $data, $status);
    }

    /** Not paid yet: the customer pays on Stitch's page. */
    public function isSuccessful(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return $this->answered() && $this->link() !== '';
    }

    /** The payment link, with the return address as `redirect_url` when one was given. */
    public function getRedirectUrl(): ?string
    {
        $link = $this->link();
        if ($link === '') {
            return null;
        }
        if ($this->returnUrl === null || $this->returnUrl === '') {
            return $link;
        }

        return $link . (str_contains($link, '?') ? '&' : '?') . 'redirect_url=' . rawurlencode($this->returnUrl);
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    /** @return array<string,mixed> */
    public function getRedirectData(): array
    {
        return [];
    }

    /** The payment link's id. */
    public function getTransactionReference(): ?string
    {
        $id = $this->part('payment')['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** When the link stops working, as Stitch gives it (ISO 8601). */
    public function getExpiresAt(): ?string
    {
        $at = $this->part('payment')['expireAt'] ?? null;

        return is_string($at) ? $at : null;
    }

    private function link(): string
    {
        $link = $this->part('payment')['link'] ?? '';

        return is_string($link) ? $link : '';
    }
}
