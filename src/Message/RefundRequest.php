<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Refunds a payment, in whole or in part, to the card it came from (POST /api/v1/payment/{paymentId}/refund): Stitch's
 * payment id (`paymentId`, as `completePurchase()` or the notification gave it), the `amount` (R1.00 at least) and a
 * `reason`: REQUESTED_BY_USER (the default), DUPLICATE, FRAUDULENT, or CANCELLED_PAYMENT for a terminal session.
 */
class RefundRequest extends AbstractRequest
{
    public const REASONS = ['DUPLICATE', 'FRAUDULENT', 'REQUESTED_BY_USER', 'CANCELLED_PAYMENT'];

    public function getPaymentId(): ?string
    {
        return $this->getParameter('paymentId');
    }

    public function setPaymentId(?string $value): static
    {
        return $this->setParameter('paymentId', $value);
    }

    public function getReason(): ?string
    {
        return $this->getParameter('reason');
    }

    public function setReason(?string $value): static
    {
        return $this->setParameter('reason', $value);
    }

    /** @return array{amount:int,reason:string} */
    public function getData(): array
    {
        $this->validate('paymentId', 'amount');
        $amount = $this->getAmountInteger();
        if ($amount < 100) {
            throw new InvalidRequestException('Stitch Express refunds R1.00 at least.');
        }
        $reason = $this->getReason() ?? 'REQUESTED_BY_USER';
        if (!in_array($reason, self::REASONS, true)) {
            throw new InvalidRequestException('A refund\'s reason is one of ' . implode(', ', self::REASONS) . '.');
        }

        return ['amount' => $amount, 'reason' => $reason];
    }

    public function send(): RefundResponse
    {
        return $this->sendData($this->getData());
    }

    /** @param array{amount:int,reason:string} $data */
    public function sendData($data): RefundResponse
    {
        [$status, $reply] = $this->call('POST', '/api/v1/payment/' . rawurlencode((string) $this->getPaymentId()) . '/refund', $data);

        return $this->response = new RefundResponse($this, $reply, $status);
    }
}
