# Changelog

What each version of omnipay-stitch-express brought, newest first.

## Unreleased

- Added: the Stitch Express gateway (`Omnipay\StitchExpress\Gateway`): `purchase()` makes a payment link and redirects the
  customer to it, with the return address as `redirect_url`; `completePurchase()` and `fetchTransaction()` ask where a link
  or a payment stands (paid only when Stitch says so, and only with the expected reference when one is given);
  `refund()` refunds a payment to its card with a reason; `acceptNotification()` reads a `payment.paid` webhook once its
  Svix signature and timestamp are checked; `redirectUrls()`, `registerRedirectUrl()`, `deleteRedirectUrl()` and
  `registerWebhook()` set an account up. Access tokens are reused for their life and renewed once when refused as
  expired. Test credentials (`test-...`) are test mode.
- Added: the account as Stitch's dashboard shows it: `listPaymentLinks()`, `listPayments()`, `listRefunds()` and `listFees()`
  (a page at a time with its cursor; by date window, status, limit and, for links, merchant reference), `fetchRefund()`,
  `fetchPaymentRefunds()` (what to look at before refunding a payment again), `fetchBalance()` and `fetchBankDetails()`.
