<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Refunds asked about: one by its id (`transactionReference`, as the refund gave it), or every refund of a payment
 * (`paymentId`), which is what to look at before refunding a payment again after a reply that never came.
 */
class FetchRefundsRequest extends AbstractRequest
{
    public function getPaymentId(): ?string
    {
        return $this->getParameter('paymentId');
    }

    public function setPaymentId(?string $value): static
    {
        return $this->setParameter('paymentId', $value);
    }

    /** @return array{refund?:string,payment?:string} */
    public function getData(): array
    {
        $refund = (string) $this->getTransactionReference();
        if ($refund !== '') {
            return ['refund' => $refund];
        }
        $payment = (string) $this->getPaymentId();
        if ($payment !== '') {
            return ['payment' => $payment];
        }
        throw new InvalidRequestException('Give the refund\'s id (transactionReference) or the payment\'s id (paymentId).');
    }

    public function send(): RefundResponse|ListResponse
    {
        return $this->sendData($this->getData());
    }

    /** @param array{refund?:string,payment?:string} $data */
    public function sendData($data): RefundResponse|ListResponse
    {
        if (isset($data['refund'])) {
            [$status, $reply] = $this->call('GET', '/api/v1/refunds/' . rawurlencode($data['refund']));

            return $this->response = new RefundResponse($this, $reply, $status);
        }
        [$status, $reply] = $this->call('GET', '/api/v1/payment/' . rawurlencode((string) ($data['payment'] ?? '')) . '/refund');

        return $this->response = new ListResponse($this, $reply, $status, 'refund');
    }
}
