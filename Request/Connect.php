<?php

namespace Omnibank\Request;

use Omnibank\Model\ConnectResult;
use Omnibank\Model\Connection;

/**
 * Open the access, or renew its consent. Result: a ConnectResult - the page
 * to send the user to (null when there is nothing to do) and the connection
 * with its new state.
 */
final class Connect extends Request
{
    public function __construct(public readonly Connection $connection, public readonly string $returnUrl)
    {
    }

    public function getConnectResult(): ?ConnectResult
    {
        return $this->getResult();
    }
}
