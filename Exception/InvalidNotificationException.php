<?php

namespace Omnibank\Exception;

/** A webhook that is not the provider's: its signature does not hold, or it is too old. */
final class InvalidNotificationException extends ProviderException
{
}
