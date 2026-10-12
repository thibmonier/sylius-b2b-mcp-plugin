<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Entity;

use Sylius\Resource\Model\ResourceInterface;

/** Audit log entry of a call to one of the B2B tools. */
interface ToolCallInterface extends ResourceInterface
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_REFUSED = 'refused';

    public const OUTCOME_ERROR = 'error';

    public const OUTCOME_REPLAYED = 'replayed';

    public const OUTCOMES = [self::OUTCOME_SUCCESS, self::OUTCOME_REFUSED, self::OUTCOME_ERROR, self::OUTCOME_REPLAYED];

    public function getId(): ?int;

    public function getConnection(): ?ConnectionInterface;

    public function getCustomerEmail(): ?string;

    public function getClientName(): ?string;

    public function getChannelCode(): ?string;

    public function getTool(): string;

    public function getArguments(): string;

    public function getOutcome(): string;

    public function getMessage(): ?string;

    public function getDurationMs(): int;

    public function getIpAddress(): ?string;

    public function getCreatedAt(): \DateTimeImmutable;
}
