<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

use Sylius\Component\Core\Model\CustomerInterface;

class ToolCall implements ToolCallInterface
{
    protected ?int $id = null;

    protected \DateTimeImmutable $createdAt;

    /** The entries go with the customer (GDPR erasure). */
    protected ?CustomerInterface $customer;

    public function __construct(
        protected ?ConnectionInterface $connection,
        protected ?string $customerEmail,
        protected ?string $clientName,
        protected ?string $channelCode,
        protected string $tool,
        protected string $arguments,
        protected string $outcome,
        protected ?string $message,
        protected int $durationMs,
        protected ?string $ipAddress,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->customer = $connection?->getCustomer();
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConnection(): ?ConnectionInterface
    {
        return $this->connection;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function getChannelCode(): ?string
    {
        return $this->channelCode;
    }

    public function getTool(): string
    {
        return $this->tool;
    }

    public function getArguments(): string
    {
        return $this->arguments;
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
