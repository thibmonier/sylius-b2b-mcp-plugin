<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * The consent of a customer to an assistant on a channel: every token issued after this
 * consent belongs to it, and revoking it invalidates them all.
 */
interface ConnectionInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getClient(): ?OAuthClient;

    public function setClient(OAuthClient $client): void;

    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(CustomerInterface $customer): void;

    public function getChannelCode(): ?string;

    public function setChannelCode(string $channelCode): void;

    /** @return list<string> */
    public function getScopes(): array;

    /** @param list<string> $scopes */
    public function setScopes(array $scopes): void;

    public function hasScope(string $scope): bool;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getLastUsedAt(): ?\DateTimeImmutable;

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): void;

    public function getRevokedAt(): ?\DateTimeImmutable;

    public function revoke(\DateTimeImmutable $now): void;

    public function isActive(): bool;
}
