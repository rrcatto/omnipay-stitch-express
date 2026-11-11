<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Tests;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\NotificationInterface;
use Omnipay\StitchExpress\AccessTokens;
use Omnipay\StitchExpress\Gateway;
use Omnipay\StitchExpress\Message\Notification;
use Omnipay\StitchExpress\Message\PurchaseResponse;
use Omnipay\StitchExpress\Message\RedirectUrlsResponse;
use Omnipay\StitchExpress\Message\RefundResponse;
use Omnipay\StitchExpress\Message\RegisterWebhookResponse;
use Omnipay\StitchExpress\Message\TransactionResponse;
use Omnipay\StitchExpress\WebhookSignature;
use Omnipay\Tests\GatewayTestCase;
use Symfony\Component\HttpFoundation\Request;

final class GatewayTest extends GatewayTestCase
{
    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    /** @var Gateway */
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        AccessTokens::forget();
        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->initialize([
            'clientId' => 'test-958fd377-3a5d-49a0-8840-eaba11ee3c19',
            'clientSecret' => 'a-test-client-secret',
            'webhookSecret' => self::SECRET,
        ]);
    }

    public function testOmnipayFindsItAsStitchExpress(): void
    {
        self::assertInstanceOf(Gateway::class, \Omnipay\Omnipay::create('StitchExpress', $this->getHttpClient(), $this->getHttpRequest()));
        self::assertSame('StitchExpress', $this->gateway->getShortName());
    }

    public function testItIsStitchExpressInRandAndTestCredentialsAreTestMode(): void
    {
        self::assertSame('Stitch Express', $this->gateway->getName());
        self::assertSame('ZAR', $this->gateway->getCurrency());
        self::assertTrue($this->gateway->getTestMode());
        $this->gateway->setClientId('live-958fd377');
        self::assertFalse($this->gateway->getTestMode());
        self::assertTrue($this->gateway->supportsPurchase());
        self::assertTrue($this->gateway->supportsCompletePurchase());
        self::assertTrue($this->gateway->supportsFetchTransaction());
        self::assertTrue($this->gateway->supportsRefund());
        self::assertTrue($this->gateway->supportsAcceptNotification());
        self::assertFalse($this->gateway->supportsAuthorize());
    }

    public function testAPurchaseMakesAPaymentLinkAndRedirectsToIt(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PurchaseSuccess.txt']);
        $response = $this->gateway->purchase([
            'amount' => '1200.00',
            'transactionId' => 'CL-1001',
            'payerName' => 'Thandi Mokoena',
            'payerEmailAddress' => 'thandi@example.com',
            'returnUrl' => 'https://shop.example.com/checkout/return',
            'expiresAt' => new \DateTimeImmutable('2026-11-11 13:00:00', new \DateTimeZone('Africa/Johannesburg')),
            'allowedPaymentMethods' => ['CARD'],
        ])->send();

        self::assertInstanceOf(PurchaseResponse::class, $response);
        self::assertFalse($response->isSuccessful());
        self::assertTrue($response->isRedirect());
        self::assertSame('GET', $response->getRedirectMethod());
        self::assertSame('https://express.stitch.money/DjbyqE?redirect_url=https%3A%2F%2Fshop.example.com%2Fcheckout%2Freturn', $response->getRedirectUrl());
        self::assertSame('pl_7xQ2', $response->getTransactionReference());
        self::assertSame('2026-11-11T11:00:00.000Z', $response->getExpiresAt());
        self::assertNull($response->getMessage(), 'A link made has nothing to say.');

        [$token, $create] = $this->getMockedRequests();
        self::assertSame('https://express.stitch.money/api/v1/token', (string) $token->getUri());
        self::assertSame(['clientId' => 'test-958fd377-3a5d-49a0-8840-eaba11ee3c19', 'clientSecret' => 'a-test-client-secret', 'scope' => 'client_paymentrequest'], json_decode((string) $token->getBody(), true));
        self::assertSame('POST', $create->getMethod());
        self::assertSame('https://express.stitch.money/api/v1/payment-links', (string) $create->getUri());
        self::assertSame('Bearer eyJhbGciOiJIUzI1NiJ9.eyJleHAiOjQxMDI0NDQ4MDB9.c2lnbmF0dXJl', $create->getHeaderLine('Authorization'));
        self::assertSame([
            'amount' => 120000,
            'payerName' => 'Thandi Mokoena',
            'merchantReference' => 'CL-1001',
            'payerEmailAddress' => 'thandi@example.com',
            'expiresAt' => '2026-11-11T11:00:00.000Z',
            'allowedPaymentMethods' => ['CARD'],
        ], json_decode((string) $create->getBody(), true));
    }

    public function testThePayerCanComeFromTheCard(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PurchaseSuccess.txt']);
        $this->gateway->purchase(['amount' => '15.50', 'transactionId' => 'ORDER 7', 'card' => ['firstName' => 'Sipho', 'lastName' => 'Dlamini', 'email' => 'sipho@example.org', 'phone' => '+27791231234']])->send();
        $body = json_decode((string) $this->getMockedRequests()[1]->getBody(), true);
        self::assertSame(['amount' => 1550, 'payerName' => 'Sipho Dlamini', 'merchantReference' => 'ORDER 7', 'payerEmailAddress' => 'sipho@example.org', 'payerPhoneNumber' => '+27791231234'], $body);
    }

    public function testAPaymentLinkWithoutAReturnAddressIsTheLinkItself(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PurchaseSuccess.txt']);
        $response = $this->gateway->purchase(['amount' => '10.00', 'transactionId' => 'A1', 'payerName' => 'Ann Lee'])->send();
        self::assertSame('https://express.stitch.money/DjbyqE', $response->getRedirectUrl());
    }

    public function testStitchsRefusalIsTheMessage(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PurchaseInvalid.txt']);
        $response = $this->gateway->purchase(['amount' => '10.00', 'transactionId' => 'A1', 'payerName' => 'Ann Lee'])->send();
        self::assertFalse($response->isRedirect());
        self::assertNull($response->getRedirectUrl());
        self::assertSame('400', $response->getCode());
        self::assertSame('payerName: must be between 3 and 40 characters; amount: must be at least 100', $response->getMessage());
    }

    public function testATooManyRequestsReplyIsAnUnsuccessfulResponse(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'RateLimited.txt']);
        $response = $this->gateway->fetchTransaction(['transactionReference' => 'pl_7xQ2'])->send();
        self::assertFalse($response->isSuccessful());
        self::assertSame('Too many requests', $response->getMessage());
    }

    public function testATokenIsReusedForItsLife(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PurchaseSuccess.txt', 'LinkPaid.txt']);
        $this->gateway->purchase(['amount' => '10.00', 'transactionId' => 'A1', 'payerName' => 'Ann Lee'])->send();
        $this->gateway->fetchTransaction(['transactionReference' => 'pl_7xQ2'])->send();
        self::assertCount(3, $this->getMockedRequests(), 'One token for both calls.');
    }

    public function testATokenRefusedAsExpiredIsRenewedOnce(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'Unauthorised.txt', 'TokenSuccess.txt', 'LinkPaid.txt']);
        $response = $this->gateway->fetchTransaction(['transactionReference' => 'pl_7xQ2'])->send();
        self::assertTrue($response->isSuccessful());
        self::assertCount(4, $this->getMockedRequests());
    }

    public function testAMerchantsOwnTokenIsUsedAsItIs(): void
    {
        $this->setMockHttpResponse(['LinkPaid.txt']);
        $this->gateway->fetchTransaction(['transactionReference' => 'pl_7xQ2', 'accessToken' => 'held-elsewhere'])->send();
        self::assertSame('Bearer held-elsewhere', $this->getMockedRequests()[0]->getHeaderLine('Authorization'));
    }

    public function testRefusedCredentialsAreThrownWithStitchsWords(): void
    {
        $this->setMockHttpResponse(['TokenRefused.txt']);
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Stitch Express refused the client ID and secret (HTTP 401): Invalid client credentials');
        $this->gateway->fetchTransaction(['transactionReference' => 'pl_7xQ2'])->send();
    }

    /** @return iterable<string,array{0:array<string,mixed>,1:string}> */
    public static function refusedPurchases(): iterable
    {
        yield 'another currency' => [['currency' => 'USD'], 'South African rand (ZAR) only'];
        yield 'under a rand' => [['amount' => '0.99'], 'R1.00 at least'];
        yield 'a reference Stitch refuses' => [['transactionId' => 'CL/1001'], '1 to 50 letters, digits, spaces or hyphens'];
        yield 'no payer' => [['payerName' => null], '3 to 40 characters'];
        yield 'a name too short' => [['payerName' => 'Al'], '3 to 40 characters'];
        yield 'an unknown method' => [['allowedPaymentMethods' => ['CARD', 'CASH']], 'not CASH'];
        yield 'no amount' => [['amount' => null], 'The amount parameter is required'];
    }

    /** @param array<string,mixed> $change */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedPurchases')]
    public function testAPurchaseStitchWouldRefuseIsNotSent(array $change, string $message): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage($message);
        try {
            $this->gateway->purchase($change + ['amount' => '10.00', 'transactionId' => 'A1', 'payerName' => 'Ann Lee'])->send();
        } finally {
            self::assertSame([], $this->getMockedRequests(), 'Nothing was sent.');
        }
    }

    public function testACompletedPurchaseIsPaidOnlyWhenStitchSaysSo(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'LinkPaid.txt']);
        $paid = $this->gateway->completePurchase(['transactionReference' => 'pl_7xQ2', 'transactionId' => 'CL-1001'])->send();
        self::assertInstanceOf(TransactionResponse::class, $paid);
        self::assertTrue($paid->isSuccessful());
        self::assertSame('PAID', $paid->getStatus());
        self::assertSame('pl_7xQ2', $paid->getTransactionReference());
        self::assertSame('pay_9kL1', $paid->getPaymentId());
        self::assertSame('CL-1001', $paid->getTransactionId());
        self::assertSame(120000, $paid->getAmountInteger());
        self::assertNull($paid->getMessage());
        self::assertSame('https://express.stitch.money/api/v1/payment-links/pl_7xQ2', (string) $this->getMockedRequests()[1]->getUri());

        foreach (['LinkPending.txt' => 'isPending', 'LinkCancelled.txt' => 'isCancelled', 'LinkExpired.txt' => 'isExpired'] as $mock => $state) {
            $this->setMockHttpResponse([$mock]);
            $response = $this->gateway->completePurchase(['transactionReference' => 'pl_7xQ2'])->send();
            self::assertFalse($response->isSuccessful(), $mock);
            self::assertTrue($response->$state(), $mock);
            self::assertNull($response->getPaymentId(), $mock);
        }
    }

    public function testTheReturnAddressNamesThePaymentWhichMustCarryTheExpectedReference(): void
    {
        $this->getHttpRequest()->query->replace(['reference' => 'CL-1001', 'payment_id' => 'pay_9kL1']);
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PaymentSettled.txt']);
        $response = $this->gateway->completePurchase(['transactionId' => 'CL-1001'])->send();
        self::assertTrue($response->isSuccessful());
        self::assertSame('SETTLED', $response->getStatus());
        self::assertSame('pay_9kL1', $response->getPaymentId());
        self::assertNull($response->getTransactionReference(), 'A payment\'s record names no link.');
        self::assertSame('https://express.stitch.money/api/v1/payment/pay_9kL1', (string) $this->getMockedRequests()[1]->getUri());

        // Someone typing another order's reference into the return address gains nothing.
        $this->setMockHttpResponse(['PaymentSettled.txt']);
        $other = $this->gateway->completePurchase(['transactionId' => 'CL-2002'])->send();
        self::assertFalse($other->isSuccessful());
        self::assertSame('The payment carries another reference than the one expected.', $other->getMessage());
    }

    public function testAnUnknownLinkIsNotFound(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'LinkNotFound.txt']);
        $response = $this->gateway->fetchTransaction(['transactionReference' => 'pl_nothing'])->send();
        self::assertFalse($response->isSuccessful());
        self::assertFalse($response->isPending());
        self::assertSame('Payment link not found', $response->getMessage());
    }

    public function testFetchingNeedsALinkOrAPayment(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->gateway->fetchTransaction()->send();
    }

    public function testARefundGoesBackToTheCard(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'RefundProcessed.txt']);
        $response = $this->gateway->refund(['paymentId' => 'pay_9kL1', 'amount' => '500.00'])->send();
        self::assertInstanceOf(RefundResponse::class, $response);
        self::assertTrue($response->isSuccessful());
        self::assertSame('PROCESSED', $response->getStatus());
        self::assertSame('6f1c2b9e-1d2a-4f3b-9c8d-7e6f5a4b3c2d', $response->getTransactionReference());
        self::assertSame(50000, $response->getAmountInteger());
        $request = $this->getMockedRequests()[1];
        self::assertSame('https://express.stitch.money/api/v1/payment/pay_9kL1/refund', (string) $request->getUri());
        self::assertSame(['amount' => 50000, 'reason' => 'REQUESTED_BY_USER'], json_decode((string) $request->getBody(), true));

        $this->setMockHttpResponse(['RefundFailed.txt']);
        $failed = $this->gateway->refund(['paymentId' => 'pay_9kL1', 'amount' => '500.00', 'reason' => 'DUPLICATE'])->send();
        self::assertFalse($failed->isSuccessful());
        self::assertSame('FAILED', $failed->getStatus());
    }

    public function testARefundNeedsAPaymentAndAKnownReason(): void
    {
        try {
            $this->gateway->refund(['amount' => '5.00'])->send();
            self::fail('A refund without a payment was sent.');
        } catch (InvalidRequestException $refused) {
            self::assertStringContainsString('paymentId', $refused->getMessage());
        }
        $this->expectExceptionMessage('A refund\'s reason is one of');
        $this->gateway->refund(['paymentId' => 'pay_9kL1', 'amount' => '5.00', 'reason' => 'CHANGED_MIND'])->send();
    }

    public function testReturnAddressesAreListedRegisteredWithoutATrailingSlashAndRemoved(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'RedirectUrls.txt', 'RedirectUrls.txt', 'RedirectUrls.txt']);
        $list = $this->gateway->redirectUrls()->send();
        self::assertInstanceOf(RedirectUrlsResponse::class, $list);
        self::assertSame(['https://shop.example.com/checkout/return', 'http://localhost:8000/return'], $list->getRedirectUrls());
        $this->gateway->registerRedirectUrl(['redirectUrl' => 'https://shop.example.com/checkout/return/'])->send();
        $this->gateway->deleteRedirectUrl(['redirectUrl' => 'http://localhost:8000/return'])->send();

        [, $get, $post, $delete] = $this->getMockedRequests();
        self::assertSame('GET', $get->getMethod());
        self::assertSame('POST', $post->getMethod());
        self::assertSame(['redirectUrl' => 'https://shop.example.com/checkout/return'], json_decode((string) $post->getBody(), true));
        self::assertSame('DELETE', $delete->getMethod());
        self::assertSame('https://express.stitch.money/api/v1/redirect-urls', (string) $delete->getUri());
    }

    public function testAReturnAddressStitchWouldRefuseIsNotSent(): void
    {
        foreach (['http://shop.example.com/return', 'https://shop.example.com/return?order=1', 'ftp://x'] as $address) {
            try {
                $this->gateway->registerRedirectUrl(['redirectUrl' => $address])->send();
                self::fail('Accepted ' . $address);
            } catch (InvalidRequestException $refused) {
                self::assertStringContainsString('https://', $refused->getMessage());
            }
        }
        self::assertSame([], $this->getMockedRequests());
    }

    public function testAWebhookIsRegisteredAndItsSecretGivenOnce(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'WebhookRegistered.txt', 'WebhookExists.txt']);
        $registered = $this->gateway->registerWebhook(['url' => 'https://shop.example.com/webhooks/stitch'])->send();
        self::assertInstanceOf(RegisterWebhookResponse::class, $registered);
        self::assertTrue($registered->isSuccessful());
        self::assertSame(self::SECRET, $registered->getSecret());
        self::assertSame(['url' => 'https://shop.example.com/webhooks/stitch'], json_decode((string) $this->getMockedRequests()[1]->getBody(), true));

        $again = $this->gateway->registerWebhook(['url' => 'https://shop.example.com/webhooks/stitch'])->send();
        self::assertFalse($again->isSuccessful());
        self::assertTrue($again->isAlreadyRegistered());
        self::assertNull($again->getSecret());
    }

    public function testAWebhookAddressMustBePublicHttps(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->gateway->registerWebhook(['url' => 'http://localhost/webhooks/stitch'])->send();
    }

    public function testTheAccountsListsComeAPageAtATimeWithTheirFilters(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PaymentLinksPage.txt', 'PaymentLinksLastPage.txt']);
        $page = $this->gateway->listPaymentLinks([
            'startTime' => new \DateTimeImmutable('2026-11-01 00:00:00', new \DateTimeZone('Africa/Johannesburg')),
            'status' => ['PAID', 'EXPIRED'],
            'limit' => 50,
        ])->send();
        self::assertTrue($page->isSuccessful());
        self::assertCount(2, $page->getItems());
        self::assertSame('pl_7xQ2', $page->getItems()[0]['id']);
        self::assertTrue($page->hasMore());
        self::assertSame('eyJvZmZzZXQiOjJ9', $page->getNextCursor());
        self::assertSame('https://express.stitch.money/api/v1/payment-links?startTime=2026-10-31T22%3A00%3A00.000Z&status=PAID&status=EXPIRED&limit=50', (string) $this->getMockedRequests()[1]->getUri());

        $next = $this->gateway->listPaymentLinks(['cursor' => $page->getNextCursor()])->send();
        self::assertSame([], $next->getItems());
        self::assertFalse($next->hasMore());
        self::assertNull($next->getNextCursor());
        self::assertStringEndsWith('/api/v1/payment-links?cursor=eyJvZmZzZXQiOjJ9', (string) $this->getMockedRequests()[2]->getUri());
    }

    public function testPaymentsRefundsAndFeesAreListed(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'PaymentsPage.txt', 'RefundsPage.txt', 'FeesPage.txt']);
        self::assertSame('pay_9kL1', $this->gateway->listPayments(['status' => 'SETTLED'])->send()->getItems()[0]['id']);
        self::assertSame('PROCESSED', $this->gateway->listRefunds()->send()->getItems()[0]['status']);
        self::assertSame(3480, $this->gateway->listFees()->send()->getItems()[0]['amount']);
        $urls = array_map(static fn ($request): string => (string) $request->getUri(), $this->getMockedRequests());
        self::assertSame(['https://express.stitch.money/api/v1/payment?status=SETTLED', 'https://express.stitch.money/api/v1/refunds', 'https://express.stitch.money/api/v1/fees'], array_slice($urls, 1));
    }

    public function testAListStitchDoesNotHaveIsRefused(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->gateway->listPayments()->setResource('orders')->send();
    }

    public function testTheAccountsBalanceAndBankAccount(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'Balance.txt', 'BankDetails.txt']);
        self::assertSame(116520, $this->gateway->fetchBalance()->send()->getBalance());
        $bank = $this->gateway->fetchBankDetails()->send();
        self::assertSame(['Example Learning (Pty) Ltd', 'fnb', '62012345678'], [$bank->getAccountName(), $bank->getBankId(), $bank->getAccountNumber()]);
        self::assertStringEndsWith('/api/v1/account/bank-details', (string) $this->getMockedRequests()[2]->getUri());
    }

    public function testARefundAndAPaymentsRefundsAreFetched(): void
    {
        $this->setMockHttpResponse(['TokenSuccess.txt', 'RefundFetched.txt', 'PaymentRefunds.txt']);
        $refund = $this->gateway->fetchRefund(['transactionReference' => '6f1c2b9e-1d2a-4f3b-9c8d-7e6f5a4b3c2d'])->send();
        self::assertInstanceOf(RefundResponse::class, $refund);
        self::assertTrue($refund->isSuccessful());
        $refunds = $this->gateway->fetchPaymentRefunds(['paymentId' => 'pay_9kL1'])->send();
        self::assertInstanceOf(\Omnipay\StitchExpress\Message\ListResponse::class, $refunds);
        self::assertSame(50000, (int) $refunds->getItems()[0]['amount']);
        self::assertStringEndsWith('/api/v1/payment/pay_9kL1/refund', (string) $this->getMockedRequests()[2]->getUri());
    }

    public function testASignedPaymentPaidDeliveryIsRead(): void
    {
        $notification = $this->gateway->acceptNotification();
        self::assertInstanceOf(Notification::class, $notification);
        $notification = new Notification($this->delivery(time()), self::SECRET);
        self::assertTrue($notification->isValid());
        self::assertSame(NotificationInterface::STATUS_COMPLETED, $notification->getTransactionStatus());
        self::assertSame('pl_7xQ2', $notification->getTransactionReference());
        self::assertSame('pay_9kL1', $notification->getPaymentId());
        self::assertSame('LINK', $notification->getType());
        self::assertSame(120000, $notification->getAmountInteger());
        self::assertSame('msg_2rTxwk', $notification->getMessageId());
        self::assertSame('Paid', $notification->getMessage());
    }

    public function testTheGatewayReadsTheDeliveryItWasGiven(): void
    {
        $gateway = new Gateway($this->getHttpClient(), $this->delivery(time()));
        $gateway->setWebhookSecret(self::SECRET);
        self::assertSame('pay_9kL1', $gateway->acceptNotification()->getPaymentId());
    }

    /** @return iterable<string,array{0:\Closure(Request):Request}> */
    public static function forgedDeliveries(): iterable
    {
        yield 'a changed body' => [static function (Request $request): Request {
            $forged = Request::create((string) $request->getUri(), 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], str_replace('120000', '1', (string) $request->getContent()));
            foreach (['svix-id', 'svix-timestamp', 'svix-signature'] as $header) $forged->headers->set($header, (string) $request->headers->get($header));

            return $forged;
        }];
        yield 'no signature' => [static function (Request $request): Request {
            $request->headers->remove('svix-signature');

            return $request;
        }];
        yield 'another secret' => [static function (Request $request): Request {
            $request->headers->set('svix-signature', WebhookSignature::sign('whsec_' . base64_encode('another secret'), 'msg_2rTxwk', (string) $request->headers->get('svix-timestamp'), (string) $request->getContent()));

            return $request;
        }];
        yield 'another delivery\'s id' => [static function (Request $request): Request {
            $request->headers->set('svix-id', 'msg_other');

            return $request;
        }];
    }

    /** @param \Closure(Request):Request $forge */
    #[\PHPUnit\Framework\Attributes\DataProvider('forgedDeliveries')]
    public function testAForgedDeliveryIsRefusedBeforeAnythingIsRead(\Closure $forge): void
    {
        $notification = new Notification($forge($this->delivery(time())), self::SECRET);
        self::assertFalse($notification->isValid());
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('not signed by Stitch Express');
        $notification->getTransactionStatus();
    }

    public function testAnOldDeliveryCannotBeReplayed(): void
    {
        $notification = new Notification($this->delivery(time() - 600), self::SECRET);
        self::assertFalse($notification->isValid());
        $this->expectException(InvalidRequestException::class);
        $notification->getPaymentId();
    }

    public function testADeliveryCannotBeCheckedWithoutASecret(): void
    {
        $this->expectExceptionMessage('no webhookSecret');
        (new Notification($this->delivery(time()), ''))->getTransactionStatus();
    }

    /** A payment.paid delivery as Svix sends it, signed at $timestamp with the test secret. */
    private function delivery(int $timestamp): Request
    {
        $body = '{"id":"pay_9kL1","status":"PAID","amount":120000,"type":"LINK","linkId":"pl_7xQ2","consentId":null,"subscriptionId":null,"terminalSessionId":null}';
        $request = Request::create('https://shop.example.com/webhooks/stitch', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
        $request->headers->set('svix-id', 'msg_2rTxwk');
        $request->headers->set('svix-timestamp', (string) $timestamp);
        $request->headers->set('svix-signature', 'v1,bm90IHRoaXMgb25l ' . WebhookSignature::sign(self::SECRET, 'msg_2rTxwk', (string) $timestamp, $body));

        return $request;
    }
}
