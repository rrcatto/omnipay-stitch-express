<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Asks Stitch where a payment stands: by the payment link's id (`transactionReference`, as the purchase gave it), the
 * merchant's own record, or by Stitch's payment id (`paymentId`).
 */
class FetchTransactionRequest extends AbstractRequest
{
    public function getPaymentId(): ?string
    {
        return $this->getParameter('paymentId');
    }

    public function setPaymentId(?string $value): static
    {
        return $this->setParameter('paymentId', $value);
    }

    /** @return array{link?:string,payment?:string} */
    public function getData(): array
    {
        $link = (string) $this->getTransactionReference();
        if ($link !== '') {
            return ['link' => $link];
        }
        $payment = (string) $this->getPaymentId();
        if ($payment !== '') {
            return ['payment' => $payment];
        }
        throw new InvalidRequestException('Give the payment link\'s id (transactionReference) or the payment\'s id (paymentId).');
    }

    public function send(): TransactionResponse
    {
        return $this->sendData($this->getData());
    }

    /** @param array{link?:string,payment?:string} $data */
    public function sendData($data): TransactionResponse
    {
        [$status, $reply] = isset($data['link'])
            ? $this->call('GET', '/api/v1/payment-links/' . rawurlencode($data['link']))
            : $this->call('GET', '/api/v1/payment/' . rawurlencode((string) ($data['payment'] ?? '')));

        return $this->response = new TransactionResponse($this, $reply, $status, $this->getTransactionId());
    }
}
