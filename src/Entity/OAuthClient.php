<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

/**
 * An OAuth client (an assistant such as Claude or ChatGPT), usually registered dynamically
 * (RFC 7591). Confidential clients keep a hash of their secret only.
 */
class OAuthClient
{
    private ?int $id = null;

    private \DateTimeImmutable $createdAt;

    /** @param list<string> $redirectUris */
    public function __construct(
        private string $identifier,
        private string $name,
        private array $redirectUris,
        private ?string $secretHash = null,
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
        return array_values($this->redirectUris);
    }

    public function getSecretHash(): ?string
    {
        return $this->secretHash;
    }

    public function isConfidential(): bool
    {
        return null !== $this->secretHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
