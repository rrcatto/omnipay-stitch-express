<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use DateTimeInterface;
use DateTimeZone;
use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Lists an account's payment links, payments, refunds or fees, newest first, a page at a time. Stitch filters each by its
 * own date (a link's and a refund's creation, a payment's payment, a fee's creation) within `startTime` and `endTime`
 * (the last 30 days, from midnight South African time, when neither is given), by `status` (one or several), and payment
 * links by `merchantReference`; `limit` is 1 to 500 (100 when not given). The next page is asked for with the same filters
 * and `cursor`, as the response gives it, while `hasMore()`.
 */
class ListRequest extends AbstractRequest
{
    /** What each list is called in Stitch's address and its answer. */
    public const RESOURCES = [
        'payment-links' => ['/api/v1/payment-links', 'payments'],
        'payments' => ['/api/v1/payment', 'payments'],
        'refunds' => ['/api/v1/refunds', 'refund'],
        'fees' => ['/api/v1/fees', 'fees'],
    ];

    public function getResource(): string
    {
        $resource = $this->getParameter('resource');

        return is_string($resource) ? $resource : '';
    }

    /** payment-links, payments, refunds or fees. */
    public function setResource(string $value): static
    {
        return $this->setParameter('resource', $value);
    }

    public function getStartTime(): DateTimeInterface|string|null
    {
        return $this->getParameter('startTime');
    }

    public function setStartTime(DateTimeInterface|string|null $value): static
    {
        return $this->setParameter('startTime', $value);
    }

    public function getEndTime(): DateTimeInterface|string|null
    {
        return $this->getParameter('endTime');
    }

    public function setEndTime(DateTimeInterface|string|null $value): static
    {
        return $this->setParameter('endTime', $value);
    }

    public function getUpdatedAfter(): DateTimeInterface|string|null
    {
        return $this->getParameter('updatedAfter');
    }

    public function setUpdatedAfter(DateTimeInterface|string|null $value): static
    {
        return $this->setParameter('updatedAfter', $value);
    }

    /** @return string|list<string>|null */
    public function getStatus(): string|array|null
    {
        return $this->getParameter('status');
    }

    /** @param string|list<string>|null $value */
    public function setStatus(string|array|null $value): static
    {
        return $this->setParameter('status', $value);
    }

    public function getLimit(): ?int
    {
        return $this->getParameter('limit');
    }

    public function setLimit(?int $value): static
    {
        return $this->setParameter('limit', $value);
    }

    public function getCursor(): ?string
    {
        return $this->getParameter('cursor');
    }

    public function setCursor(?string $value): static
    {
        return $this->setParameter('cursor', $value);
    }

    public function getMerchantReference(): ?string
    {
        return $this->getParameter('merchantReference');
    }

    public function setMerchantReference(?string $value): static
    {
        return $this->setParameter('merchantReference', $value);
    }

    /** @return array<string,scalar|list<scalar>> the query Stitch is asked with */
    public function getData(): array
    {
        if (!isset(self::RESOURCES[$this->getResource()])) {
            throw new InvalidRequestException('A list is of payment-links, payments, refunds or fees.');
        }
        $query = [];
        foreach (['startTime' => $this->getStartTime(), 'endTime' => $this->getEndTime(), 'updatedAfter' => $this->getUpdatedAfter()] as $key => $at) {
            if ($at instanceof DateTimeInterface) {
                $query[$key] = \DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
            } elseif (is_string($at) && $at !== '') {
                $query[$key] = $at;
            }
        }
        $status = $this->getStatus();
        if ($status !== null && $status !== '' && $status !== []) {
            $query['status'] = $status;
        }
        $limit = $this->getLimit();
        if ($limit !== null) {
            if ($limit < 1 || $limit > 500) {
                throw new InvalidRequestException('A list\'s limit is 1 to 500.');
            }
            $query['limit'] = $limit;
        }
        if ((string) $this->getCursor() !== '') {
            $query['cursor'] = (string) $this->getCursor();
        }
        if ((string) $this->getMerchantReference() !== '') {
            $query['merchantReference'] = (string) $this->getMerchantReference();
        }

        return $query;
    }

    public function send(): ListResponse
    {
        return $this->sendData($this->getData());
    }

    /** @param array<string,scalar|list<scalar>> $data */
    public function sendData($data): ListResponse
    {
        [$path, $key] = self::RESOURCES[$this->getResource()];
        [$status, $reply] = $this->call('GET', $path, null, $data);

        return $this->response = new ListResponse($this, $reply, $status, $key);
    }
}
