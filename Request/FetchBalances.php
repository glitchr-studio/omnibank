<?php

namespace Omnibank\Request;

use Omnibank\Model\Account;
use Omnibank\Model\Balance;
use Omnibank\Model\Connection;

/** An account's balances as the provider knows them. Result: list<Balance>. */
final class FetchBalances extends Request
{
    public function __construct(public readonly Connection $connection, public readonly Account $account)
    {
    }

    /** @return list<Balance> */
    public function getBalances(): array
    {
        return $this->getResult() ?? [];
    }
}
