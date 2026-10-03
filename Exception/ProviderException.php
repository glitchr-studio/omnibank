<?php

namespace Omnibank\Exception;

/** The provider refused: wrong credentials, an unknown account, a file it cannot read. */
class ProviderException extends \RuntimeException implements OmnibankException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        /** The provider's own error code, when it gave one */
        public readonly ?string $providerCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('[%s] %s', $provider, $message), 0, $previous);
    }
}
