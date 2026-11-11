<?php

declare(strict_types=1);

namespace Omnipay\StitchExpress\Message;

/** The account's balance in cents, or its bank account: the holder's name, the bank and the account number. */
class AccountResponse extends Response
{
    public function getBalance(): ?int
    {
        $balance = $this->part()['balance'] ?? null;

        return is_numeric($balance) ? (int) $balance : null;
    }

    public function getAccountName(): ?string
    {
        return $this->bank('name');
    }

    /** Stitch's name for the bank, such as fnb or capitec. */
    public function getBankId(): ?string
    {
        return $this->bank('bankId');
    }

    public function getAccountNumber(): ?string
    {
        return $this->bank('accountNumber');
    }

    private function bank(string $key): ?string
    {
        $value = $this->part('bankDetails')[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
