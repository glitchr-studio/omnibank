<?php

namespace Omnibank\Model;

/**
 * A SEPA credit transfer to make: from the debtor's account to the
 * creditor's. $amount is what is sent, positive. $reference is the
 * remittance information the creditor sees (140 characters at most);
 * $endToEndId travels with the money to the creditor's bank.
 */
final readonly class Transfer
{
    public function __construct(
        public string $debtorIban,
        public string $creditorName,
        public string $creditorIban,
        public ?string $creditorBic,
        public Money $amount,
        public string $reference,
        public ?string $endToEndId = null,
        /** When the bank should execute it; today when null. */
        public ?\DateTimeImmutable $executionDate = null,
    ) {
    }
}
