<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

/** A refund: successful once Stitch has processed it; its transaction reference is the refund's id. */
class RefundResponse extends Response
{
    public function isSuccessful(): bool
    {
        return $this->answered() && $this->getStatus() === 'PROCESSED';
    }

    /** PROCESSED or FAILED. */
    public function getStatus(): ?string
    {
        $status = $this->part('refund')['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public function getTransactionReference(): ?string
    {
        $id = $this->part('refund')['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** The amount refunded, in cents. */
    public function getAmountInteger(): ?int
    {
        $amount = $this->part('refund')['amount'] ?? null;

        return is_numeric($amount) ? (int) $amount : null;
    }
}
