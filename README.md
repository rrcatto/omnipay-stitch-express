# Omnipay: Stitch Express

**[Stitch Express](https://express.stitch.money) gateway for the [Omnipay](https://omnipay.thephpleague.com) payment
processing library.**

Stitch Express takes South African payments (card, Capitec Pay, buy now pay later, Apple Pay and Google Pay) through
payment links: the merchant makes a link for an amount, sends the customer to it, and Stitch sends the customer back and
announces the payment with a signed webhook. This package does each of those through Omnipay's usual calls.

## Requirements

PHP 8.1 or later, [`omnipay/common`](https://github.com/thephpleague/omnipay-common) 3.5, and an HTTP client Omnipay can
use (any `php-http/client-implementation`, such as `php-http/guzzle7-adapter` or `symfony/http-client` with a PSR-17
factory).

## Installation

```sh
composer require league/omnipay rrcatto/omnipay-stitch-express
```

## Basic use

The gateway is `Stitch`. Its parameters are your Stitch Express client ID and secret (Settings, Developers; test
credentials start with `test-` and are test mode) and, for webhooks, the signing secret `registerWebhook()` gave you.

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('StitchExpress');
$gateway->initialize([
    'clientId' => 'test-958fd377-3a5d-49a0-8840-eaba11ee3c19',
    'clientSecret' => '...',
    'webhookSecret' => 'whsec_...',
]);
```

### Taking a payment

```php
$response = $gateway->purchase([
    'amount' => '1200.00',                  // rand; R1.00 at least
    'transactionId' => 'ORDER-1001',        // your reference: letters, digits, spaces, hyphens, up to 50
    'payerName' => 'Thandi Mokoena',        // 3 to 40 characters (or a card's name)
    'payerEmailAddress' => 'thandi@example.com',
    'returnUrl' => 'https://shop.example.com/checkout/return',
    'expiresAt' => new DateTimeImmutable('+1 hour'),   // optional; Stitch's default is 30 days
    'allowedPaymentMethods' => ['CARD'],    // optional: CARD, CAPITEC, BNPL, APPLE_PAY, GOOGLE_PAY
])->send();

if ($response->isRedirect()) {
    $linkId = $response->getTransactionReference();   // keep it with the order
    $response->redirect();                            // or send the customer to getRedirectUrl()
} else {
    echo $response->getMessage();                     // Stitch's own words
}
```

The return address must be registered with Stitch first, exactly as you give it and without a query string (Stitch
compares it byte for byte after dropping the query string and one trailing slash; an unregistered address shows the
customer an error after paying). Up to five can be registered; `http://localhost` is accepted for testing:

```php
$gateway->registerRedirectUrl(['redirectUrl' => 'https://shop.example.com/checkout/return'])->send();
$gateway->redirectUrls()->send()->getRedirectUrls();
$gateway->deleteRedirectUrl(['redirectUrl' => 'https://shop.example.com/checkout/return'])->send();
```

### When the customer returns

Stitch sends the customer to the return address with `reference` (your transactionId) and `payment_id`. The return
proves nothing by itself: ask Stitch, by the link's id you kept.

```php
$response = $gateway->completePurchase(['transactionReference' => $linkId])->send();

if ($response->isSuccessful()) {          // PAID
    $paymentId = $response->getPaymentId();   // keep it: refunds are made against it
} elseif ($response->isPending()) {       // not paid yet
} elseif ($response->isCancelled() || $response->isExpired()) {
}
```

Without the link's id, `completePurchase(['transactionId' => 'ORDER-1001'])` fetches the payment named in the return
address and accepts it only when it carries that reference. `fetchTransaction()` asks the same at any time, by
`transactionReference` (a link) or `paymentId` (a payment).

### Webhooks

Stitch sends one event, `payment.paid`, to the address you register; it must be public and on https. The signing secret
is shown once, when you register:

```php
$secret = $gateway->registerWebhook(['url' => 'https://shop.example.com/webhooks/stitch'])->send()->getSecret();
```

Each delivery is signed by [Svix](https://docs.svix.com/receiving/introduction). The notification checks the signature
against the raw body and refuses a forged, altered or old (over five minutes) delivery before anything is read:

```php
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\NotificationInterface;

try {
    $notification = $gateway->acceptNotification();
    if ($notification->getTransactionStatus() === NotificationInterface::STATUS_COMPLETED) {
        $linkId = $notification->getTransactionReference();   // the link (or consent, subscription, terminal session)
        $paymentId = $notification->getPaymentId();
        $once = $notification->getMessageId();                // the same for every retry of one event
    }
    http_response_code(200);
} catch (InvalidRequestException $refused) {
    http_response_code(400);
}
```

Stitch retries a delivery not answered with 2xx for about a day, so act once per `getMessageId()`.

### Refunds

```php
$response = $gateway->refund([
    'paymentId' => $paymentId,
    'amount' => '500.00',
    'reason' => 'REQUESTED_BY_USER',   // or DUPLICATE, FRAUDULENT, CANCELLED_PAYMENT (terminal sessions)
])->send();

$response->isSuccessful();             // PROCESSED
```

### The account

What Stitch's dashboard shows, through the API, newest first and a page at a time (the last 30 days unless a window is
given; `limit` up to 500):

```php
$page = $gateway->listPaymentLinks(['startTime' => new DateTimeImmutable('-7 days'), 'status' => ['PAID', 'PENDING']])->send();
foreach ($page->getItems() as $link) { /* id, amount, status, merchantReference, paymentId, ... */ }
if ($page->hasMore()) {
    $next = $gateway->listPaymentLinks(['startTime' => /* the same filters */ null, 'cursor' => $page->getNextCursor()])->send();
}

$gateway->listPayments()->send()->getItems();     // paid and settled payments, with fees and the card's last four digits
$gateway->listRefunds()->send()->getItems();
$gateway->listFees()->send()->getItems();
$gateway->fetchPaymentRefunds(['paymentId' => $paymentId])->send()->getItems();   // before refunding a payment again
$gateway->fetchBalance()->send()->getBalance();   // cents
$gateway->fetchBankDetails()->send()->getAccountNumber();
```

### Access tokens

Each call needs a token from Stitch, valid for 15 minutes. The gateway asks for one, reuses it for its life within the
process and renews it once if Stitch refuses it as expired. A merchant that holds a token elsewhere passes it as
`accessToken`. Refused credentials are thrown as an `InvalidResponseException` with Stitch's words; every other refusal
is an unsuccessful response whose `getMessage()` gives Stitch's words and `getCode()` the HTTP status.

## Testing

```sh
composer install
composer check   # PHPUnit and PHPStan
```

The tests use Omnipay's test harness with Stitch's replies recorded in `tests/Mock`, and Svix's own published example
for the signature.

## Support

Report a fault or ask for a feature in this repository's issues. For Stitch Express itself (your account, test payments,
going live), see [Stitch Express](https://express.stitch.money) and its [API documentation](https://express.stitch.money/api-docs).

## Licence

MIT; see [LICENSE](LICENSE).
