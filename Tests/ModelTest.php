<?php

namespace Omnibank\Tests;

use Omnibank\Model\Connection;
use Omnibank\Model\Consent;
use Omnibank\Model\ConsentStatus;
use Omnibank\Model\Money;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testMoneyIsSignedMinorUnits(): void
    {
        self::assertTrue(Money::of(-1250, 'eur')->isNegative());
        self::assertFalse(Money::of(0, 'EUR')->isNegative());
        self::assertSame('EUR', Money::of(1250, 'eur')->currency);
        self::assertSame('-12.50', Money::of(-1250, 'EUR')->decimal());
        self::assertSame('0.05', Money::of(5, 'EUR')->decimal());
        self::assertSame('1250', Money::of(1250, 'JPY')->decimal());
        self::assertSame('1.250', Money::of(1250, 'KWD')->decimal());
        self::assertSame('-0.07 EUR', (string) Money::of(-7, 'EUR'));
        self::assertTrue(Money::of(-1250, 'EUR')->abs()->equals(Money::of(1250, 'EUR')));
        self::assertTrue(Money::of(1250, 'EUR')->negate()->isNegative());
    }

    public function testMoneyReadsDecimalsWithoutFloats(): void
    {
        self::assertSame(123456, Money::fromDecimal('1234.56', 'EUR')->amount);
        self::assertSame(-50, Money::fromDecimal('-0.5', 'EUR')->amount);
        self::assertSame(-50, Money::fromDecimal('-.5', 'EUR')->amount);
        self::assertSame(1000, Money::fromDecimal('10', 'EUR')->amount);
        self::assertSame(13, Money::fromDecimal('0.125', 'EUR')->amount, 'half up beyond the cents');
        self::assertSame(1250, Money::fromDecimal(12.5, 'EUR')->amount);
        self::assertSame(1250, Money::fromDecimal('1250', 'JPY')->amount);
        self::assertSame(1999, Money::fromDecimal(19.99, 'EUR')->amount, 'a float rounded, not truncated');

        $this->expectException(\InvalidArgumentException::class);
        Money::fromDecimal('1 234,56', 'EUR');
    }

    public function testAConnectionTravelsAsAnArrayOrSerialized(): void
    {
        $connection = new Connection();
        self::assertSame(ConsentStatus::NONE, $connection->consent->status);
        self::assertSame([], $connection->state);

        $kept = $connection->withState(['user' => 'u1', 'token' => 'secret'])->withConsent(Consent::active(new \DateTimeImmutable('2027-01-01T00:00:00+00:00')));
        self::assertSame([], $connection->state, 'immutable');
        self::assertSame('u1', $kept->get('user'));
        self::assertSame(['user' => 'u2'], $kept->withState(['user' => 'u2'])->state, 'withState() replaces');
        self::assertSame(ConsentStatus::ACTIVE, $kept->withState([])->consent->status, 'the consent stays');

        $json = json_encode($kept->toArray());
        $back = Connection::fromArray(json_decode($json, true));
        self::assertEquals($kept, $back);
        self::assertEquals($kept, unserialize(serialize($kept)));
        self::assertSame(['status' => 'active', 'expires_at' => '2027-01-01T00:00:00+00:00'], $kept->toArray()['consent']);
        self::assertSame(ConsentStatus::NONE, Connection::fromArray([])->consent->status);
    }

    public function testAConsentIsActiveUntilItExpires(): void
    {
        $consent = Consent::active(new \DateTimeImmutable('2026-12-31'));
        self::assertTrue($consent->isActive(new \DateTimeImmutable('2026-10-01')));
        self::assertFalse($consent->isActive(new \DateTimeImmutable('2027-01-01')));
        self::assertTrue(Consent::active()->isActive());
        self::assertFalse(Consent::needsRenewal()->isActive());
        self::assertFalse(Consent::revoked()->isActive());
        self::assertSame(ConsentStatus::REVOKED, Consent::fromArray(['status' => 'revoked'])->status);
    }
}
