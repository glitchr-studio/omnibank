<?php

namespace Omnibank\Model;

/**
 * An amount in a currency's minor units (cents), signed: -1250 EUR is
 * 12,50 € gone out of the account.
 */
final readonly class Money
{
    public string $currency;

    public function __construct(public int $amount, string $currency)
    {
        $this->currency = strtoupper($currency);
    }

    public static function of(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isZero(): bool
    {
        return 0 === $this->amount;
    }

    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->amount), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /** In major units, as a bank file or a decimal API expects ("-12.50"); yen and the like have none. */
    public function decimal(): string
    {
        $digits = self::minorDigits($this->currency);
        $units = (string) abs($this->amount);
        if ($digits > 0) {
            $units = str_pad($units, $digits + 1, '0', \STR_PAD_LEFT);
            $units = substr($units, 0, -$digits).'.'.substr($units, -$digits);
        }

        return ($this->amount < 0 ? '-' : '').$units;
    }

    /**
     * From a decimal ("12.50", "-0.5", 12.5), to minor units. A string is read
     * digit by digit, never through a float, so "1234.56" is exactly 123456.
     */
    public static function fromDecimal(string|int|float $decimal, string $currency): self
    {
        $digits = self::minorDigits($currency);
        if (!\is_string($decimal)) {
            return new self((int) round($decimal * (10 ** $digits)), $currency);
        }
        $decimal = trim($decimal);
        if (!preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $decimal, $m) || '' === $m[2].($m[3] ?? '')) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a decimal amount.', $decimal));
        }
        $fraction = $m[3] ?? '';
        $minor = (int) (($m[2] ?: '0').str_pad(substr($fraction, 0, $digits), $digits, '0'));
        // Rounds half up on what lies beyond the currency's digits.
        if (\strlen($fraction) > $digits && (int) $fraction[$digits] >= 5) {
            ++$minor;
        }

        return new self('-' === $m[1] ? -$minor : $minor, $currency);
    }

    /** How many minor digits a currency has: 2 for most, 0 for JPY, KRW..., 3 for BHD, KWD... */
    public static function minorDigits(string $currency): int
    {
        return match (strtoupper($currency)) {
            'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' => 0,
            'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' => 3,
            default => 2,
        };
    }

    public function __toString(): string
    {
        return $this->decimal().' '.$this->currency;
    }
}
