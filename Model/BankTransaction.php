<?php

namespace Omnibank\Model;

/**
 * One booked line of an account. $amount is negative when money leaves the
 * account. $id is stable - the bank's reference, or a hash of the line when
 * it has none - so the same line read twice is the same transaction.
 */
final readonly class BankTransaction
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $bookedOn,
        public ?\DateTimeImmutable $valueOn,
        public Money $amount,
        /** The bank's label for the line, as it shows on the statement. */
        public string $label,
        public ?string $counterpartyName,
        public ?string $counterpartyIban,
        /** What the payer wrote: the remittance information, an end-to-end id. */
        public ?string $reference,
        public array $raw = [],
    ) {
    }

    public function isDebit(): bool
    {
        return $this->amount->isNegative();
    }
}
