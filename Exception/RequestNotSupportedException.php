<?php

namespace Omnibank\Exception;

use Omnibank\Request\Request;

/** The provider does not do that (no transfers, no webhooks...). */
final class RequestNotSupportedException extends \LogicException implements OmnibankException
{
    public static function for(Request $request, string $gateway): self
    {
        return new self(\sprintf('The "%s" gateway does not support %s.', $gateway, (new \ReflectionClass($request))->getShortName()));
    }
}
