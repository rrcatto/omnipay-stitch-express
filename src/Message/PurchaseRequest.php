<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use DateTimeInterface;
use DateTimeZone;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Creates a payment link (POST /api/v1/payment-links), to which the customer is sent to pay.
 *
 * - `amount` and `currency` (ZAR, the only currency Stitch Express takes; R1.00 at least), and `transactionId`, the
 *   merchant's own reference for the payment (letters, digits, spaces and hyphens, up to 50), which Stitch hands back when
 *   the customer returns.
 * - The payer's name (3 to 40 characters): `payerName`, or else the card's name; the payer's email address and phone
 *   number likewise, both optional.
 * - `returnUrl`: where the customer is sent after paying or giving up. Stitch requires it to be registered first, exactly
 *   as given and without a query string (`registerRedirectUrl()`); it is added to the link as `redirect_url`, and Stitch
 *   adds `reference` and `payment_id` when it sends the customer back.
 * - Optional: `expiresAt` (a date and time, else Stitch's 30 days), `allowedPaymentMethods` (CARD, CAPITEC, BNPL,
 *   APPLE_PAY, GOOGLE_PAY), `skipCheckoutPage`, `collectDeliveryDetails` with `deliveryFee` in cents.
 */
class PurchaseRequest extends AbstractRequest
{
    public const PAYMENT_METHODS = ['CARD', 'CAPITEC', 'BNPL', 'APPLE_PAY', 'GOOGLE_PAY'];

    public function getPayerName(): ?string
    {
        return $this->getParameter('payerName');
    }

    public function setPayerName(?string $value): static
    {
        return $this->setParameter('payerName', $value);
    }

    public function getPayerEmailAddress(): ?string
    {
        return $this->getParameter('payerEmailAddress');
    }

    public function setPayerEmailAddress(?string $value): static
    {
        return $this->setParameter('payerEmailAddress', $value);
    }

    public function getPayerPhoneNumber(): ?string
    {
        return $this->getParameter('payerPhoneNumber');
    }

    public function setPayerPhoneNumber(?string $value): static
    {
        return $this->setParameter('payerPhoneNumber', $value);
    }

    public function getExpiresAt(): DateTimeInterface|string|null
    {
        return $this->getParameter('expiresAt');
    }

    public function setExpiresAt(DateTimeInterface|string|null $value): static
    {
        return $this->setParameter('expiresAt', $value);
    }

    /** @return list<string>|null */
    public function getAllowedPaymentMethods(): ?array
    {
        return $this->getParameter('allowedPaymentMethods');
    }

    /** @param list<string>|null $value */
    public function setAllowedPaymentMethods(?array $value): static
    {
        return $this->setParameter('allowedPaymentMethods', $value);
    }

    public function getSkipCheckoutPage(): ?bool
    {
        return $this->getParameter('skipCheckoutPage');
    }

    public function setSkipCheckoutPage(?bool $value): static
    {
        return $this->setParameter('skipCheckoutPage', $value);
    }

    public function getCollectDeliveryDetails(): ?bool
    {
        return $this->getParameter('collectDeliveryDetails');
    }

    public function setCollectDeliveryDetails(?bool $value): static
    {
        return $this->setParameter('collectDeliveryDetails', $value);
    }

    /** The delivery fee in cents, with collectDeliveryDetails. */
    public function getDeliveryFee(): ?int
    {
        return $this->getParameter('deliveryFee');
    }

    public function setDeliveryFee(?int $value): static
    {
        return $this->setParameter('deliveryFee', $value);
    }

    /** @return array<string,mixed> */
    public function getData(): array
    {
        $this->validate('amount', 'transactionId');
        if ($this->getCurrency() !== 'ZAR') {
            throw new InvalidRequestException('Stitch Express takes payments in South African rand (ZAR) only.');
        }
        $amount = $this->getAmountInteger();
        if ($amount < 100) {
            throw new InvalidRequestException('Stitch Express takes R1.00 at least.');
        }
        $reference = (string) $this->getTransactionId();
        if (preg_match('/^[a-zA-Z0-9 \-]{1,50}$/', $reference) !== 1) {
            throw new InvalidRequestException('The transactionId must be 1 to 50 letters, digits, spaces or hyphens: Stitch Express takes no other merchant reference.');
        }
        // The card is optional: Omnipay declares it always there, but a purchase need not have one.
        $card = $this->getParameter('card');
        $card = $card instanceof CreditCard ? $card : null;
        $name = trim((string) ($this->getPayerName() ?? $card?->getName()));
        $length = mb_strlen($name);
        if ($length < 3 || $length > 40) {
            throw new InvalidRequestException('The payer\'s name must be 3 to 40 characters (payerName, or the card\'s name).');
        }

        $data = ['amount' => $amount, 'payerName' => $name, 'merchantReference' => $reference];
        $email = trim((string) ($this->getPayerEmailAddress() ?? $card?->getEmail()));
        if ($email !== '') $data['payerEmailAddress'] = $email;
        $phone = trim((string) ($this->getPayerPhoneNumber() ?? $card?->getPhone()));
        if ($phone !== '') $data['payerPhoneNumber'] = $phone;
        $expires = $this->getExpiresAt();
        if ($expires instanceof DateTimeInterface) {
            $data['expiresAt'] = \DateTimeImmutable::createFromInterface($expires)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
        } elseif (is_string($expires) && $expires !== '') {
            $data['expiresAt'] = $expires;
        }
        $methods = $this->getAllowedPaymentMethods();
        if ($methods !== null) {
            $unknown = array_diff($methods, self::PAYMENT_METHODS);
            if ($methods === [] || $unknown !== []) {
                throw new InvalidRequestException('allowedPaymentMethods takes ' . implode(', ', self::PAYMENT_METHODS) . ($unknown !== [] ? '; not ' . implode(', ', $unknown) : '') . '.');
            }
            $data['allowedPaymentMethods'] = $methods;
        }
        if ($this->getSkipCheckoutPage() !== null) $data['skipCheckoutPage'] = $this->getSkipCheckoutPage();
        if ($this->getCollectDeliveryDetails() !== null) {
            $data['collectDeliveryDetails'] = $this->getCollectDeliveryDetails();
            if ($this->getCollectDeliveryDetails()) $data['deliveryFee'] = $this->getDeliveryFee() ?? 0;
        }

        return $data;
    }

    public function send(): PurchaseResponse
    {
        return $this->sendData($this->getData());
    }

    /** @param array<string,mixed> $data */
    public function sendData($data): PurchaseResponse
    {
        [$status, $reply] = $this->call('POST', '/api/v1/payment-links', $data);

        return $this->response = new PurchaseResponse($this, $reply, $status, $this->getReturnUrl());
    }
}
