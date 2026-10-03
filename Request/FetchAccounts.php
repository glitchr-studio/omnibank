<?php

namespace Omnibank\Request;

use Omnibank\Model\Account;
use Omnibank\Model\Connection;

/** The accounts the connection reaches. Result: list<Account>. */
final class FetchAccounts extends Request
{
    public function __construct(public readonly Connection $connection)
    {
    }

    /** @return list<Account> */
    public function getAccounts(): array
    {
        return $this->getResult() ?? [];
    }
}
