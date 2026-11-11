<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Message\RequestInterface;

/**
 * Where a payment stands, from a payment link (PENDING, PAID, EXPIRED, CANCELLED) or a payment (PAID, SETTLED).
 * Successful only when it is paid, and, when the merchant said which reference to expect, carries that reference.
 */
class TransactionResponse extends Response
{
    /** @param array<string,mixed> $data */
    public function __construct(RequestInterface $request, array $data, int $status, private readonly ?string $expectedReference = null)
    {
        parent::__construct($request, $data, $status);
    }

    public function isSuccessful(): bool
    {
        return $this->answered() && in_array($this->getStatus(), ['PAID', 'SETTLED'], true) && $this->referenceMatches();
    }

    public function isPending(): bool
    {
        return $this->answered() && $this->getStatus() === 'PENDING';
    }

    public function isCancelled(): bool
    {
        return $this->answered() && $this->getStatus() === 'CANCELLED';
    }

    public function isExpired(): bool
    {
        return $this->answered() && $this->getStatus() === 'EXPIRED';
    }

    public function getMessage(): ?string
    {
        if ($this->answered() && !$this->referenceMatches()) {
            return 'The payment carries another reference than the one expected.';
        }

        return parent::getMessage();
    }

    /** PENDING, PAID, EXPIRED or CANCELLED for a link; PAID or SETTLED for a payment. */
    public function getStatus(): ?string
    {
        $status = $this->record()['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    /** Whether this is a payment link's record (else a payment's). */
    public function isLink(): bool
    {
        return array_key_exists('link', $this->record()) || array_key_exists('paymentId', $this->record());
    }

    /** The payment link's id, for a link's record. */
    public function getTransactionReference(): ?string
    {
        return $this->isLink() ? $this->text('id') : null;
    }

    /** Stitch's id of the payment: a link's once it is paid, or the payment's own. Refunds are made against it. */
    public function getPaymentId(): ?string
    {
        return $this->isLink() ? $this->text('paymentId') : $this->text('id');
    }

    /** The merchant's own reference, as given when the link was made. */
    public function getTransactionId(): ?string
    {
        return $this->text('merchantReference');
    }

    /** The amount in cents. */
    public function getAmountInteger(): ?int
    {
        $amount = $this->record()['amount'] ?? null;

        return is_int($amount) ? $amount : (is_numeric($amount) ? (int) $amount : null);
    }

    public function getPaidAt(): ?string
    {
        return $this->text('paidAt');
    }

    /** @return array<string,mixed> */
    private function record(): array
    {
        return $this->part('payment');
    }

    private function referenceMatches(): bool
    {
        return $this->expectedReference === null || $this->expectedReference === '' || $this->getTransactionId() === $this->expectedReference;
    }

    private function text(string $key): ?string
    {
        $value = $this->record()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
