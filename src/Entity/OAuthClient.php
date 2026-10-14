<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

/**
 * An OAuth client (an assistant such as Claude or ChatGPT), usually registered dynamically
 * (RFC 7591). Confidential clients keep a hash of their secret only.
 */
class OAuthClient
{
    protected ?int $id = null;

    private readonly \DateTimeImmutable $createdAt;

    /** @param list<string> $redirectUris */
    public function __construct(
        private readonly string $identifier,
        private readonly string $name,
        private readonly array $redirectUris,
        private readonly ?string $secretHash = null,
        private readonly bool $dynamicallyRegistered = true,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<string> */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    public function getSecretHash(): ?string
    {
        return $this->secretHash;
    }

    public function isConfidential(): bool
    {
        return null !== $this->secretHash;
    }

    /** Registered by anyone through RFC 7591 (its name is self-declared), not by the merchant. */
    public function isDynamicallyRegistered(): bool
    {
        return $this->dynamicallyRegistered;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
