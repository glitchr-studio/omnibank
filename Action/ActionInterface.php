<?php

namespace Omnibank\Action;

use Omnibank\Request\Request;

/** One thing a provider does: answers the requests it supports. */
interface ActionInterface
{
    public function supports(Request $request): bool;

    /** Sets the request's result, or throws Omnibank\Exception\ProviderException. */
    public function execute(Request $request): void;
}
