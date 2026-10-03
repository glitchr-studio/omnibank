<?php

namespace Omnibank\Model;

/** The user's consent: where it stands, and until when when the provider says. */
final readonly class Consent
{
    public function __construct(
        public ConsentStatus $status,
        public ?\DateTimeImmutable $expiresAt = null,
    ) {
    }

    public static function none(): self
    {
        return new self(ConsentStatus::NONE);
    }

    public static function active(?\DateTimeImmutable $expiresAt = null): self
    {
        return new self(ConsentStatus::ACTIVE, $expiresAt);
    }

    public static function needsRenewal(?\DateTimeImmutable $expiresAt = null): self
    {
        return new self(ConsentStatus::NEEDS_RENEWAL, $expiresAt);
    }

    public static function revoked(): self
    {
        return new self(ConsentStatus::REVOKED);
    }

    /** Active, and not past its expiry date (at $now, or now). */
    public function isActive(?\DateTimeInterface $now = null): bool
    {
        return ConsentStatus::ACTIVE === $this->status
            && (null === $this->expiresAt || $this->expiresAt > ($now ?? new \DateTimeImmutable()));
    }

    /** @return array{status: string, expires_at: ?string} */
    public function toArray(): array
    {
        return ['status' => $this->status->value, 'expires_at' => $this->expiresAt?->format(\DATE_ATOM)];
    }

    /** @param array{status?: string, expires_at?: ?string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            ConsentStatus::tryFrom((string) ($data['status'] ?? '')) ?? ConsentStatus::NONE,
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );
    }
}
