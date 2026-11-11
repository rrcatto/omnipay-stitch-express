<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress;

use Omnipay\Common\AbstractGateway;
use Omnipay\StitchExpress\Message\AccountRequest;
use Omnipay\StitchExpress\Message\CompletePurchaseRequest;
use Omnipay\StitchExpress\Message\FetchRefundsRequest;
use Omnipay\StitchExpress\Message\ListRequest;
use Omnipay\StitchExpress\Message\FetchTransactionRequest;
use Omnipay\StitchExpress\Message\Notification;
use Omnipay\StitchExpress\Message\PurchaseRequest;
use Omnipay\StitchExpress\Message\RedirectUrlsRequest;
use Omnipay\StitchExpress\Message\RefundRequest;
use Omnipay\StitchExpress\Message\RegisterWebhookRequest;

/**
 * Stitch Express (https://express.stitch.money): South African card, Capitec Pay, buy-now-pay-later, Apple Pay and
 * Google Pay payments through payment links.
 *
 * - `purchase()` makes a payment link and redirects the customer to it; `completePurchase()` asks where it stands when the
 *   customer is back; `fetchTransaction()` asks at any time; `refund()` refunds a paid payment to its card.
 * - `acceptNotification()` reads a signed `payment.paid` webhook.
 * - Setting up: `redirectUrls()` lists the return addresses Stitch accepts, `registerRedirectUrl()` and
 *   `deleteRedirectUrl()` change them, and `registerWebhook()` registers the webhook address and gives its signing secret.
 * - The account as Stitch's dashboard shows it: `listPaymentLinks()`, `listPayments()`, `listRefunds()` and `listFees()`,
 *   a page at a time; `fetchRefund()` and `fetchPaymentRefunds()`; `fetchBalance()` and `fetchBankDetails()`.
 *
 * Parameters: `clientId` and `clientSecret` (Stitch's developer settings; test credentials start with "test-" and are
 * test mode), `webhookSecret` (whsec_..., from registering the webhook), and `currency`, ZAR.
 */
class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'Stitch Express';
    }

    /** @return array<string,mixed> */
    public function getDefaultParameters(): array
    {
        return [
            'clientId' => '',
            'clientSecret' => '',
            'webhookSecret' => '',
        ];
    }

    /** ZAR unless set: the only currency Stitch Express takes. */
    public function getCurrency(): string
    {
        $currency = $this->getParameter('currency');

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : 'ZAR';
    }

    public function getClientId(): ?string
    {
        return $this->getParameter('clientId');
    }

    public function setClientId(?string $value): static
    {
        return $this->setParameter('clientId', $value);
    }

    public function getClientSecret(): ?string
    {
        return $this->getParameter('clientSecret');
    }

    public function setClientSecret(?string $value): static
    {
        return $this->setParameter('clientSecret', $value);
    }

    public function getWebhookSecret(): ?string
    {
        return $this->getParameter('webhookSecret');
    }

    public function setWebhookSecret(?string $value): static
    {
        return $this->setParameter('webhookSecret', $value);
    }

    /** Test mode as set, else as the credentials say: Stitch's test client IDs start with "test-". */
    public function getTestMode(): bool
    {
        $mode = $this->getParameter('testMode');

        return $mode !== null ? (bool) $mode : str_starts_with((string) $this->getClientId(), 'test-');
    }

    /** @param array<string,mixed> $options */
    public function purchase(array $options = []): PurchaseRequest
    {
        /** @var PurchaseRequest */
        return $this->createRequest(PurchaseRequest::class, $options);
    }

    /** @param array<string,mixed> $options */
    public function completePurchase(array $options = []): CompletePurchaseRequest
    {
        /** @var CompletePurchaseRequest */
        return $this->createRequest(CompletePurchaseRequest::class, $options);
    }

    /** @param array<string,mixed> $options */
    public function fetchTransaction(array $options = []): FetchTransactionRequest
    {
        /** @var FetchTransactionRequest */
        return $this->createRequest(FetchTransactionRequest::class, $options);
    }

    /** @param array<string,mixed> $options */
    public function refund(array $options = []): RefundRequest
    {
        /** @var RefundRequest */
        return $this->createRequest(RefundRequest::class, $options);
    }

    /** @param array<string,mixed> $options */
    public function acceptNotification(array $options = []): Notification
    {
        return new Notification($this->httpRequest, $options['webhookSecret'] ?? $this->getWebhookSecret());
    }

    /** @param array<string,mixed> $options */
    public function redirectUrls(array $options = []): RedirectUrlsRequest
    {
        /** @var RedirectUrlsRequest */
        return $this->createRequest(RedirectUrlsRequest::class, ['action' => 'list'] + $options);
    }

    /** @param array<string,mixed> $options with redirectUrl */
    public function registerRedirectUrl(array $options = []): RedirectUrlsRequest
    {
        /** @var RedirectUrlsRequest */
        return $this->createRequest(RedirectUrlsRequest::class, ['action' => 'register'] + $options);
    }

    /** @param array<string,mixed> $options with redirectUrl */
    public function deleteRedirectUrl(array $options = []): RedirectUrlsRequest
    {
        /** @var RedirectUrlsRequest */
        return $this->createRequest(RedirectUrlsRequest::class, ['action' => 'delete'] + $options);
    }

    /** @param array<string,mixed> $options startTime, endTime, status, limit, cursor, merchantReference, updatedAfter */
    public function listPaymentLinks(array $options = []): ListRequest
    {
        /** @var ListRequest */
        return $this->createRequest(ListRequest::class, ['resource' => 'payment-links'] + $options);
    }

    /** @param array<string,mixed> $options startTime, endTime, status, limit, cursor, updatedAfter */
    public function listPayments(array $options = []): ListRequest
    {
        /** @var ListRequest */
        return $this->createRequest(ListRequest::class, ['resource' => 'payments'] + $options);
    }

    /** @param array<string,mixed> $options startTime, endTime, status, limit, cursor, updatedAfter */
    public function listRefunds(array $options = []): ListRequest
    {
        /** @var ListRequest */
        return $this->createRequest(ListRequest::class, ['resource' => 'refunds'] + $options);
    }

    /** @param array<string,mixed> $options startTime, endTime, limit, cursor, updatedAfter */
    public function listFees(array $options = []): ListRequest
    {
        /** @var ListRequest */
        return $this->createRequest(ListRequest::class, ['resource' => 'fees'] + $options);
    }

    /** @param array<string,mixed> $options with transactionReference, the refund's id */
    public function fetchRefund(array $options = []): FetchRefundsRequest
    {
        /** @var FetchRefundsRequest */
        return $this->createRequest(FetchRefundsRequest::class, $options);
    }

    /** @param array<string,mixed> $options with paymentId */
    public function fetchPaymentRefunds(array $options = []): FetchRefundsRequest
    {
        /** @var FetchRefundsRequest */
        return $this->createRequest(FetchRefundsRequest::class, $options);
    }

    /** @param array<string,mixed> $options */
    public function fetchBalance(array $options = []): AccountRequest
    {
        /** @var AccountRequest */
        return $this->createRequest(AccountRequest::class, ['what' => 'balance'] + $options);
    }

    /** @param array<string,mixed> $options */
    public function fetchBankDetails(array $options = []): AccountRequest
    {
        /** @var AccountRequest */
        return $this->createRequest(AccountRequest::class, ['what' => 'bank-details'] + $options);
    }

    /** @param array<string,mixed> $options with url */
    public function registerWebhook(array $options = []): RegisterWebhookRequest
    {
        /** @var RegisterWebhookRequest */
        return $this->createRequest(RegisterWebhookRequest::class, $options);
    }
}
