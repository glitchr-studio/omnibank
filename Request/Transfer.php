<?php

namespace Omnibank\Request;

use Omnibank\Model\Connection;
use Omnibank\Model\Transfer as TransferModel;
use Omnibank\Model\TransferResult;

/**
 * Send money: a SEPA credit transfer. Result: a TransferResult - what the
 * provider made of it, and the file to hand the bank when it is one.
 */
final class Transfer extends Request
{
    public function __construct(public readonly Connection $connection, public readonly TransferModel $transfer)
    {
    }

    public function getTransferResult(): ?TransferResult
    {
        return $this->getResult();
    }
}
