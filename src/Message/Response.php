<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;

/**
 * A Stitch Express reply: its HTTP status and its JSON. Stitch answers `{"success": true, "data": {...}}`, or
 * `{"success": false, "generalErrors": [...], "fieldErrors": {...}}`, whose words are the response's message.
 */
class Response extends AbstractResponse
{
    /** @param array<string,mixed> $data */
    public function __construct(RequestInterface $request, array $data, private readonly int $status)
    {
        parent::__construct($request, $data);
    }

    /** @return array<string,mixed> */
    public function getData(): array
    {
        $data = parent::getData();

        return is_array($data) ? $data : [];
    }

    public function isSuccessful(): bool
    {
        return $this->answered();
    }

    /** Whether Stitch accepted the call (HTTP 2xx and success true), whatever the response then makes of it. */
    public function answered(): bool
    {
        return $this->status >= 200 && $this->status < 300 && ($this->getData()['success'] ?? false) === true;
    }

    /** The HTTP status Stitch answered with. */
    public function getCode(): string
    {
        return (string) $this->status;
    }

    public function getMessage(): ?string
    {
        $words = self::errors($this->getData());
        if ($words !== '') {
            return $words;
        }

        return $this->answered() ? null : 'Stitch Express answered with HTTP ' . $this->status . '.';
    }

    /**
     * The `data` object of a successful reply, or one named part of it.
     *
     * @return array<string,mixed>
     */
    protected function part(?string $name = null): array
    {
        $data = $this->getData()['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }
        if ($name === null) {
            return $data;
        }
        $part = $data[$name] ?? [];

        return is_array($part) ? $part : [];
    }

    /**
     * Stitch's error words in one line: its general errors, then each field's ("amount: must be at least 100").
     *
     * @param array<string,mixed> $data
     */
    public static function errors(array $data): string
    {
        $words = [];
        foreach ((array) ($data['generalErrors'] ?? []) as $error) {
            if (is_string($error) && $error !== '') $words[] = $error;
        }
        foreach ((array) ($data['fieldErrors'] ?? []) as $field => $errors) {
            foreach ((array) $errors as $error) {
                if (is_string($error) && $error !== '') $words[] = $field . ': ' . $error;
            }
        }
        if ($words === [] && is_string($data['message'] ?? null) && $data['message'] !== '') {
            $words[] = $data['message'];
        }

        return implode('; ', $words);
    }
}
