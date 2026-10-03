<?php

namespace Omnibank\Model;

/**
 * A bank account as the provider knows it. $id is the provider's (what
 * balances() and transactions() are asked with); $raw keeps the provider's
 * whole answer, for what this model leaves out.
 */
final readonly class Account
{
    public function __construct(
        public string $id,
        public ?string $iban,
        public ?string $bic,
        public string $name,
        public string $currency,
        /** The provider's word for it: "checking", "savings", "card"... */
        public ?string $type,
        public array $raw = [],
    ) {
    }
}
