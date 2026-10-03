<?php

namespace Omnibank\Request;

use Omnibank\Model\Account;
use Omnibank\Model\BankTransaction;
use Omnibank\Model\Connection;

/**
 * An account's booked transactions, between two dates when given (both
 * included, on the booking date). Result: list<BankTransaction>, oldest first.
 */
final class FetchTransactions extends Request
{
    public function __construct(
        public readonly Connection $connection,
        public readonly Account $account,
        public readonly ?\DateTimeInterface $since = null,
        public readonly ?\DateTimeInterface $until = null,
    ) {
    }

    /** Whether a booking date falls between since and until, days compared. */
    public function covers(\DateTimeInterface $bookedOn): bool
    {
        $day = $bookedOn->format('Y-m-d');

        return (null === $this->since || $day >= $this->since->format('Y-m-d'))
            && (null === $this->until || $day <= $this->until->format('Y-m-d'));
    }

    /** @return list<BankTransaction> */
    public function getTransactions(): array
    {
        return $this->getResult() ?? [];
    }
}
