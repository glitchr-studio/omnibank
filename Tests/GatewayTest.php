<?php

namespace Omnibank\Tests;

use Omnibank\Exception\InvalidConfigException;
use Omnibank\Exception\RequestNotSupportedException;
use Omnibank\Model\Connection;
use Omnibank\Model\ConsentStatus;
use Omnibank\Model\Transfer as TransferModel;
use Omnibank\Model\Money;
use Omnibank\Registry;
use Omnibank\Request\Connect;
use Omnibank\Request\FetchAccounts;
use Omnibank\Request\Notify;
use Omnibank\Request\Transfer;
use PHPUnit\Framework\TestCase;

final class GatewayTest extends TestCase
{
    public function testConnectGivesThePageAndTheConnectionToKeep(): void
    {
        $gateway = (new StubFactory())->create(['token' => 't']);

        $result = $gateway->connect(new Connection(), 'https://app.example/bank/back');

        self::assertTrue($result->isRedirect());
        self::assertSame('https://bank.example/consent?back=https%3A%2F%2Fapp.example%2Fbank%2Fback', $result->url);
        self::assertSame('t_1', $result->connection->get('user'), 'the state the provider set comes back');
        self::assertSame(ConsentStatus::ACTIVE, $result->connection->consent->status);

        $accounts = $gateway->accounts($result->connection);
        self::assertSame('acc_t_1', $accounts[0]->id);
        self::assertTrue($gateway->supports(Connect::class));
        self::assertTrue($gateway->supports(FetchAccounts::class));
        self::assertFalse($gateway->supports(Notify::class));
    }

    public function testTransactionsAreKeptBetweenTheDatesAsked(): void
    {
        $gateway = (new StubFactory())->create(['token' => 't']);
        $account = $gateway->accounts(new Connection())[0];

        self::assertCount(2, $gateway->transactions(new Connection(), $account));
        $september = $gateway->transactions(new Connection(), $account, new \DateTimeImmutable('2026-09-15 18:00'), new \DateTime('2026-09-30'));
        self::assertSame(['t2'], array_map(static fn ($t) => $t->id, $september), 'days compared, both ends included');
        self::assertTrue($gateway->transactions(new Connection(), $account, until: new \DateTimeImmutable('2026-09-01'))[0]->isDebit());
    }

    public function testAnUnsupportedRequestSaysWhichGatewayAndWhat(): void
    {
        $gateway = (new StubFactory())->create(['token' => 't']);
        self::assertFalse($gateway->supports(Transfer::class));

        $this->expectException(RequestNotSupportedException::class);
        $this->expectExceptionMessage('The "stub" gateway does not support Transfer.');
        $gateway->transfer(new Connection(), new TransferModel('FR7630006000011234567890189', 'Camille Durand', 'FR1420041010050500013M02606', null, Money::of(1250, 'EUR'), 'Facture 42'));
    }

    public function testRequiredOptionsAreChecked(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "stub" gateway needs: token.');
        (new StubFactory())->create();
    }

    public function testTheRegistryBuildsEachGatewayOnceByName(): void
    {
        $registry = new Registry([new StubFactory()], ['treasury' => ['factory' => 'stub', 'options' => ['token' => 'a']]]);

        self::assertTrue($registry->has('treasury'));
        self::assertFalse($registry->has('savings'));
        self::assertSame(['treasury'], $registry->names());
        self::assertSame($registry->get('treasury'), $registry->get('treasury'));
        self::assertSame('stub', $registry->get('treasury')->getName());
        self::assertSame(['stub'], $registry->factories());
        self::assertSame(['token' => 'a'], $registry->options('treasury'));

        // Typed elsewhere (a back office), over the configured options: a fresh gateway, not kept.
        $typed = $registry->create('treasury', ['token' => 'b']);
        self::assertNotSame($registry->get('treasury'), $typed);
        self::assertSame('b_1', $typed->connect(new Connection(), 'https://app.example')->connection->get('user'));

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No "savings" gateway; configured: treasury.');
        $registry->get('savings');
    }

    public function testAnUnknownFactoryIsNamed(): void
    {
        $registry = new Registry([new StubFactory()], ['treasury' => ['factory' => 'qonto']]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No "qonto" factory for the "treasury" gateway; installed: stub.');
        $registry->get('treasury');
    }
}
