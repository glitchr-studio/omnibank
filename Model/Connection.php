<?php

namespace Omnibank\Model;

/**
 * What the application keeps for one bank access and hands back on every
 * call: the provider's state (its user, its tokens, the statement files) and
 * the consent. Stored serialized - serialize() or toArray()/fromArray() for
 * JSON; it holds credentials (tokens), so keep it as you keep secrets.
 */
final readonly class Connection
{
    /** @param array<string, mixed> $state */
    public function __construct(
        public array $state = [],
        public Consent $consent = new Consent(ConsentStatus::NONE),
    ) {
    }

    /** The same connection, its state replaced by $state. */
    public function withState(array $state): self
    {
        return new self($state, $this->consent);
    }

    public function withConsent(Consent $consent): self
    {
        return new self($this->state, $consent);
    }

    /** A key of the state, or $default. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /** @return array{state: array<string, mixed>, consent: array{status: string, expires_at: ?string}} */
    public function toArray(): array
    {
        return ['state' => $this->state, 'consent' => $this->consent->toArray()];
    }

    public static function fromArray(array $data): self
    {
        return new self((array) ($data['state'] ?? []), Consent::fromArray((array) ($data['consent'] ?? [])));
    }
}
