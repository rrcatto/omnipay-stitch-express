<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Message\RequestInterface;

/** A page of a list: its records as Stitch gives them, and the cursor of the next page while there is one. */
class ListResponse extends Response
{
    /** @param array<string,mixed> $data */
    public function __construct(RequestInterface $request, array $data, int $status, private readonly string $key)
    {
        parent::__construct($request, $data, $status);
    }

    /** @return list<array<string,mixed>> */
    public function getItems(): array
    {
        $items = $this->part()[$this->key] ?? [];

        return array_values(array_filter(is_array($items) ? $items : [], 'is_array'));
    }

    public function hasMore(): bool
    {
        return ($this->pagination()['hasMore'] ?? false) === true;
    }

    public function getNextCursor(): ?string
    {
        $cursor = $this->pagination()['nextCursor'] ?? null;

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /** @return array<string,mixed> */
    private function pagination(): array
    {
        $pagination = $this->getData()['pagination'] ?? [];

        return is_array($pagination) ? $pagination : [];
    }
}
