<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

/**
 * An access token, refresh token or authorization code. Only a SHA-256 hash of the identifier
 * is stored: a database leak does not reveal usable access tokens.
 */
class OAuthToken
{
    public const TYPE_ACCESS = 'access';

    public const TYPE_REFRESH = 'refresh';

    public const TYPE_AUTH_CODE = 'code';

    protected ?int $id = null;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $revokedAt = null;

    /** @param list<string> $scopes */
    public function __construct(
        private string $type,
        private string $identifierHash,
        private ConnectionInterface $connection,
        private array $scopes,
        private \DateTimeImmutable $expiresAt,
        private ?string $parentHash = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function hash(string $identifier): string
    {
        return hash('sha256', $identifier);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIdentifierHash(): string
    {
        return $this->identifierHash;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /** Hash of the access token a refresh token was issued with. */
    public function getParentHash(): ?string
    {
        return $this->parentHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    public function isUsableAt(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $this->expiresAt > $now && $this->connection->isActive();
    }
}
