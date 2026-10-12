<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

/** Result of a cart-changing tool call, replayed when the assistant retries with the same key. */
class IdempotencyRecord
{
    private ?int $id = null;

    private ?string $response = null;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        private int $customerId,
        private string $tool,
        private string $idempotencyKey,
        private string $requestHash,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
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
