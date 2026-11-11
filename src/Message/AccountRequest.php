<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * The account itself: its balance in cents (`balance`), or the verified bank account Stitch pays withdrawals and
 * settlements into (`bank-details`).
 */
class AccountRequest extends AbstractRequest
{
    public function getWhat(): string
    {
        $what = $this->getParameter('what');

        return is_string($what) ? $what : 'balance';
    }

    /** balance or bank-details. */
    public function setWhat(string $value): static
    {
        return $this->setParameter('what', $value);
    }

    /** @return array{} */
    public function getData(): array
    {
        if (!in_array($this->getWhat(), ['balance', 'bank-details'], true)) {
            throw new InvalidRequestException('The account gives its balance or its bank details.');
        }

        return [];
    }

    public function send(): AccountResponse
    {
        return $this->sendData($this->getData());
    }

    /** @param array{} $data */
    public function sendData($data): AccountResponse
    {
        [$status, $reply] = $this->call('GET', '/api/v1/account/' . $this->getWhat());

        return $this->response = new AccountResponse($this, $reply, $status);
    }
}
