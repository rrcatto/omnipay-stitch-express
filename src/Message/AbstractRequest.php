<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Message\AbstractRequest as OmnipayRequest;
use Omnipay\StitchExpress\AccessTokens;

/**
 * What every Stitch Express request shares: the client credentials, the API's address and a short-lived access token.
 *
 * Stitch Express authenticates with a bearer token from POST /api/v1/token (client ID and secret), valid for 15 minutes.
 * A token is reused for its life ({@see AccessTokens}); one refused as expired is replaced once and the call repeated.
 * A reply the API sends with an error status becomes an unsuccessful response with Stitch's words as its message, as
 * Omnipay expects; only a failure to reach the API at all is thrown.
 */
abstract class AbstractRequest extends OmnipayRequest
{
    public const ENDPOINT = 'https://express.stitch.money';

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

    /** The API's address; Stitch Express has one for test and live credentials alike. */
    public function getEndpoint(): string
    {
        $endpoint = $this->getParameter('endpoint');

        return is_string($endpoint) && $endpoint !== '' ? rtrim($endpoint, '/') : self::ENDPOINT;
    }

    public function setEndpoint(?string $value): static
    {
        return $this->setParameter('endpoint', $value);
    }

    /** An access token the merchant already holds, used instead of asking for one. */
    public function getAccessToken(): ?string
    {
        return $this->getParameter('accessToken');
    }

    public function setAccessToken(?string $value): static
    {
        return $this->setParameter('accessToken', $value);
    }

    /** Test mode as set, else as the credentials say: Stitch's test client IDs start with "test-". */
    public function getTestMode(): bool
    {
        $mode = $this->getParameter('testMode');

        return $mode !== null ? (bool) $mode : str_starts_with((string) $this->getClientId(), 'test-');
    }

    /** ZAR unless set: the only currency Stitch Express takes. */
    public function getCurrency(): string
    {
        $currency = $this->getParameter('currency');

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : 'ZAR';
    }

    /** The scope a token for this request needs; payment links, payments and refunds share the default. */
    protected function scope(): string
    {
        return 'client_paymentrequest';
    }

    /**
     * Calls the API and gives back its HTTP status and its decoded JSON (an empty array for a body that is not JSON). A
     * query's lists are sent as repeated keys (status=PAID&status=SETTLED), as Stitch reads them.
     *
     * @param array<string,mixed>|null $body
     * @param array<string,scalar|list<scalar>> $query
     * @return array{0:int,1:array<string,mixed>}
     */
    protected function call(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $one) {
                $pairs[] = rawurlencode($key) . '=' . rawurlencode(is_bool($one) ? ($one ? 'true' : 'false') : (string) $one);
            }
        }
        if ($pairs !== []) {
            $path .= '?' . implode('&', $pairs);
        }
        [$status, $data] = $this->callWith($this->token(false), $method, $path, $body);
        if ($status === 401 && $this->getAccessToken() === null) {
            // The token ran out between asking and using it: ask once more.
            [$status, $data] = $this->callWith($this->token(true), $method, $path, $body);
        }

        return [$status, $data];
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array{0:int,1:array<string,mixed>}
     */
    private function callWith(string $token, string $method, string $path, ?array $body): array
    {
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        $response = $this->httpClient->request($method, $this->getEndpoint() . $path, $headers, $body === null ? null : (string) json_encode($body, JSON_UNESCAPED_SLASHES));

        return [$response->getStatusCode(), self::decode((string) $response->getBody())];
    }

    /** An access token for this request's credentials and scope: the merchant's, a held one, or a new one. */
    private function token(bool $renew): string
    {
        $given = $this->getAccessToken();
        if ($given !== null && $given !== '') {
            return $given;
        }
        $this->validate('clientId', 'clientSecret');
        $key = AccessTokens::key((string) $this->getClientId(), (string) $this->getClientSecret(), $this->scope(), $this->getEndpoint());
        if (!$renew && ($held = AccessTokens::held($key)) !== null) {
            return $held;
        }
        $response = $this->httpClient->request(
            'POST',
            $this->getEndpoint() . '/api/v1/token',
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            (string) json_encode(['clientId' => $this->getClientId(), 'clientSecret' => $this->getClientSecret(), 'scope' => $this->scope()]),
        );
        $data = self::decode((string) $response->getBody());
        $token = $data['data']['accessToken'] ?? null;
        if ($response->getStatusCode() !== 200 || !is_string($token) || $token === '') {
            throw new \Omnipay\Common\Exception\InvalidResponseException('Stitch Express refused the client ID and secret (HTTP ' . $response->getStatusCode() . ')' . (($words = Response::errors($data)) !== '' ? ': ' . $words : '.'));
        }
        AccessTokens::hold($key, $token);

        return $token;
    }

    /** @return array<string,mixed> */
    private static function decode(string $body): array
    {
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }
}
