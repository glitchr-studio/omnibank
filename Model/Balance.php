<?php

namespace Omnibank\Model;

/**
 * What an account holds at a moment. $type says which balance: "booked"
 * (what has been entered), "available" (what can be spent), "expected"
 * (with what is coming), or the provider's own word.
 */
final readonly class Balance
{
    public function __construct(
        public Money $amount,
        public \DateTimeImmutable $at,
        public string $type = 'booked',
    ) {
    }
}
