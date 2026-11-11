<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\NotificationInterface;
use Omnipay\StitchExpress\WebhookSignature;
use Symfony\Component\HttpFoundation\Request;

/**
 * A `payment.paid` webhook from Stitch Express, the only event it sends: a payment that has been paid, whether through a
 * payment link, a card consent, a subscription or a terminal session.
 *
 * Nothing is read from a delivery until its Svix signature is checked against the raw body with the gateway's
 * `webhookSecret` ({@see WebhookSignature}); a delivery that fails is refused with an InvalidRequestException, which the
 * merchant answers with an error status. A delivery may arrive more than once (Stitch retries anything not answered with
 * 2xx for about a day): `getMessageId()` (svix-id) is the same for each, to act once. Answer 2xx promptly.
 */
class Notification implements NotificationInterface
{
    /** @var array<string,mixed>|null */
    private ?array $data = null;

    public function __construct(private readonly Request $request, private readonly ?string $secret, private readonly ?int $now = null)
    {
    }

    /** Whether the delivery is genuine and recent. */
    public function isValid(): bool
    {
        return WebhookSignature::valid(
            (string) $this->secret,
            $this->header('svix-id'),
            $this->header('svix-timestamp'),
            $this->header('svix-signature'),
            (string) $this->request->getContent(),
            $this->now,
        );
    }

    /**
     * The event's payload, once its signature is checked.
     *
     * @return array<string,mixed>
     */
    public function getData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        if ((string) $this->secret === '') {
            throw new InvalidRequestException('The gateway has no webhookSecret to check the delivery with.');
        }
        if (!$this->isValid()) {
            throw new InvalidRequestException('The webhook delivery is not signed by Stitch Express, or is too old.');
        }
        $data = json_decode((string) $this->request->getContent(), true);
        if (!is_array($data)) {
            throw new InvalidRequestException('The webhook delivery is not JSON.');
        }
        // Svix wraps nothing; accept a payload under "data" as well, in case Stitch adds an envelope.
        if (!isset($data['status']) && is_array($data['data'] ?? null)) {
            $data = $data['data'];
        }

        return $this->data = $data;
    }

    /** COMPLETED for a paid payment; Stitch sends nothing else. */
    public function getTransactionStatus(): string
    {
        return ($this->getData()['status'] ?? null) === 'PAID' ? self::STATUS_COMPLETED : self::STATUS_PENDING;
    }

    /**
     * What the payment came from, as the merchant knows it: the payment link's id for a link (the purchase's transaction
     * reference), else the consent's, subscription's or terminal session's id.
     */
    public function getTransactionReference(): ?string
    {
        $data = $this->getData();
        foreach (['linkId', 'consentId', 'subscriptionId', 'terminalSessionId'] as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return null;
    }

    /** Stitch's id of the payment, which refunds are made against. */
    public function getPaymentId(): ?string
    {
        $id = $this->getData()['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** LINK, CONSENT, SUBSCRIPTION or TERMINAL. */
    public function getType(): ?string
    {
        $type = $this->getData()['type'] ?? null;

        return is_string($type) ? $type : null;
    }

    /** The amount paid, in cents. */
    public function getAmountInteger(): ?int
    {
        $amount = $this->getData()['amount'] ?? null;

        return is_numeric($amount) ? (int) $amount : null;
    }

    /** The delivery's id (svix-id), the same for each retry of one event. */
    public function getMessageId(): string
    {
        return $this->header('svix-id');
    }

    public function getMessage(): ?string
    {
        return $this->getTransactionStatus() === self::STATUS_COMPLETED ? 'Paid' : null;
    }

    private function header(string $name): string
    {
        $value = $this->request->headers->get($name);

        return is_string($value) ? $value : '';
    }
}
