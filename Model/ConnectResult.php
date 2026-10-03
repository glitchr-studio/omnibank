<?php

namespace Omnibank\Model;

/**
 * What connect() gives back: the page to send the user to (a provider's
 * webview, a connect session) or null when there is nothing to do, and the
 * connection to keep - its state may have changed (a user created, a token).
 */
final readonly class ConnectResult
{
    public function __construct(
        public ?string $url,
        public Connection $connection,
    ) {
    }

    public function isRedirect(): bool
    {
        return null !== $this->url;
    }
}
