<?php

namespace Omnibank\Tests;

use Omnibank\Action\ActionInterface;
use Omnibank\Action\ApiAwareInterface;
use Omnibank\Action\ApiAwareTrait;
use Omnibank\Config;
use Omnibank\GatewayFactory;
use Omnibank\Model\Account;
use Omnibank\Model\BankTransaction;
use Omnibank\Model\Consent;
use Omnibank\Model\ConnectResult;
use Omnibank\Model\Money;
use Omnibank\Request\Connect;
use Omnibank\Request\FetchAccounts;
use Omnibank\Request\FetchTransactions;
use Omnibank\Request\Request;

/** A bank with one account, for the tests: its API is a counter, connect() hands out a token. */
final class StubFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibank.factory_name' => 'stub',
            'omnibank.factory_title' => 'Stub',
            'omnibank.required_options' => ['token'],
            'omnibank.api' => static fn (Config $c) => new StubApi($c['token']),
            'omnibank.action.connect' => new StubConnectAction(),
            'omnibank.action.accounts' => static fn (Config $c) => new StubAccountsAction(),
            'omnibank.action.transactions' => new StubTransactionsAction(),
        ]);
    }
}

final class StubApi
{
    public int $calls = 0;

    public function __construct(public readonly string $token)
    {
    }
}

final class StubConnectAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<StubApi> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = StubApi::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Connect;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Connect);
        ++$this->api->calls;
        $connection = $request->connection->withState(['user' => $this->api->token.'_'.$this->api->calls] + $request->connection->state);
        $request->setResult(new ConnectResult('https://bank.example/consent?back='.urlencode($request->returnUrl), $connection->withConsent(Consent::active())));
    }
}

final class StubAccountsAction implements ActionInterface
{
    public function supports(Request $request): bool
    {
        return $request instanceof FetchAccounts;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchAccounts);
        $request->setResult([new Account('acc_'.$request->connection->get('user', '?'), 'FR7630006000011234567890189', 'AGRIFRPP', 'Compte courant', 'EUR', 'checking')]);
    }
}

final class StubTransactionsAction implements ActionInterface
{
    public function supports(Request $request): bool
    {
        return $request instanceof FetchTransactions;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchTransactions);
        $all = [
            new BankTransaction('t1', new \DateTimeImmutable('2026-09-01'), null, Money::of(-4590, 'EUR'), 'PRLV SEPA EDF', 'EDF', null, null),
            new BankTransaction('t2', new \DateTimeImmutable('2026-09-15'), null, Money::of(120000, 'EUR'), 'VIR SEPA RECU', 'Camille Durand', 'FR1420041010050500013M02606', 'Facture 42'),
        ];
        $request->setResult(array_values(array_filter($all, static fn (BankTransaction $t) => $request->covers($t->bookedOn))));
    }
}
