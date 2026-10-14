<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;

class Connection implements ConnectionInterface
{
    protected ?int $id = null;

    protected ?OAuthClient $client = null;

    protected ?CustomerInterface $customer = null;

    protected ?string $channelCode = null;

    /** @var list<string> */
    protected array $scopes = [];

    protected \DateTimeImmutable $createdAt;

    protected ?\DateTimeImmutable $lastUsedAt = null;

    protected ?\DateTimeImmutable $revokedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?OAuthClient
    {
        return $this->client;
    }

    public function setClient(OAuthClient $client): void
    {
        $this->client = $client;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getChannelCode(): ?string
    {
        return $this->channelCode;
    }

    public function setChannelCode(string $channelCode): void
    {
        $this->channelCode = $channelCode;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function setScopes(array $scopes): void
    {
        $this->scopes = array_values(array_unique($scopes));
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    public function isActive(): bool
    {
        return !$this->revokedAt instanceof \DateTimeImmutable;
    }
}
