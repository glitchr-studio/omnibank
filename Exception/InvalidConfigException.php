<?php

namespace Omnibank\Exception;

/** A gateway misconfigured: a credential missing, an unknown factory. */
final class InvalidConfigException extends \LogicException implements OmnibankException
{
}
