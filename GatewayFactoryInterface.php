<?php

namespace Omnibank;

/** Builds a provider's gateway from its options (credentials, sandbox...). */
interface GatewayFactoryInterface
{
    /** The name gateways are configured with: "files", "qonto", "powens", "bridge"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): GatewayInterface;
}
