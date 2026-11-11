<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

/**
 * The customer back from Stitch's page: where the payment stands. The merchant gives the link's id it kept from the
 * purchase (`transactionReference`), the reliable way; without it, the payment Stitch named in the return address
 * (`payment_id`) is fetched, and with `transactionId` given it is accepted only when it carries that reference, since
 * anyone can type a return address. A payment is paid only when Stitch says so: the return itself proves nothing.
 */
class CompletePurchaseRequest extends FetchTransactionRequest
{
    /** @return array{link?:string,payment?:string} */
    public function getData(): array
    {
        if ((string) $this->getTransactionReference() === '' && (string) $this->getPaymentId() === '') {
            $returned = $this->httpRequest->query->get('payment_id');
            if (is_string($returned) && $returned !== '') {
                $this->setPaymentId($returned);
            }
        }

        return parent::getData();
    }
}
