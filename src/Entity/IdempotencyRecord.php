<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;

/**
 * Result of a cart-changing tool call, replayed when the assistant retries with the same key.
 * Written with DBAL by IdempotencyStore; the rows go with the customer (GDPR erasure).
 */
class IdempotencyRecord
{
    protected ?int $id = null;

    private ?string $response = null;

    private readonly \DateTimeImmutable $createdAt;

    public function __construct(
        private readonly CustomerInterface $customer,
        private readonly string $tool,
        private readonly string $idempotencyKey,
        private readonly string $requestHash,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    public function getTool(): string
    {
        return $this->tool;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(string $response): void
    {
        $this->response = $response;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
