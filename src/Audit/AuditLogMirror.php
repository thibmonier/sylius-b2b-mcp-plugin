<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Audit;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusAuditLogPlugin\Context\AuditContextProviderInterface;
use Tmonier\SyliusAuditLogPlugin\Entity\AuditLogEntryInterface;
use Tmonier\SyliusAuditLogPlugin\Recorder\AuditLogWriter;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCallInterface;

/**
 * Entries of tmonier/sylius-audit-log-plugin for the tool calls (without their arguments), the
 * consents and the revocations, owned by the customer: they appear in the history of the
 * customer and its anonymization command covers them. Written with its DBAL writer (no flush).
 * Recorded whatever its "sources" setting (customer actions are not recorded by default there):
 * these are security events the merchant asked to mirror.
 */
final class AuditLogMirror implements AuditMirrorInterface
{
    public const RESOURCE_TOOL_CALL = 'mcp_tool_call';

    public const RESOURCE_CONNECTION = 'mcp_connection';

    /** @param FactoryInterface<AuditLogEntryInterface> $entryFactory */
    public function __construct(
        private readonly FactoryInterface $entryFactory,
        private readonly AuditLogWriter $writer,
        private readonly AuditContextProviderInterface $contextProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function toolCalled(ToolCallInterface $call): void
    {
        $this->write(
            AuditLogEntryInterface::ACTION_CREATE,
            self::RESOURCE_TOOL_CALL,
            $call::class,
            $call->getId(),
            \sprintf('%s (%s)', $call->getTool(), $call->getOutcome()),
            $call->getCustomer()?->getId(),
            [
                'tool' => ['old' => null, 'new' => $call->getTool()],
                'outcome' => ['old' => null, 'new' => $call->getOutcome()],
                'assistant' => ['old' => null, 'new' => $call->getClientName()],
                'channel' => ['old' => null, 'new' => $call->getChannelCode()],
            ],
        );
    }

    public function connectionApproved(ConnectionInterface $connection, array $previousScopes): void
    {
        $this->write(
            [] === $previousScopes ? AuditLogEntryInterface::ACTION_CREATE : AuditLogEntryInterface::ACTION_UPDATE,
            self::RESOURCE_CONNECTION,
            ConnectionInterface::class,
            $connection->getId(),
            $this->label($connection),
            $connection->getCustomer()?->getId(),
            ['scopes' => ['old' => [] === $previousScopes ? null : implode(' ', $previousScopes), 'new' => implode(' ', $connection->getScopes())]],
        );
    }

    public function connectionRevoked(ConnectionInterface $connection): void
    {
        $this->write(
            AuditLogEntryInterface::ACTION_UPDATE,
            self::RESOURCE_CONNECTION,
            ConnectionInterface::class,
            $connection->getId(),
            $this->label($connection),
            $connection->getCustomer()?->getId(),
            ['revokedAt' => ['old' => null, 'new' => $connection->getRevokedAt()?->format(\DateTimeInterface::ATOM)]],
        );
    }

    /** @param array<string, array<string, mixed>> $changes */
    private function write(string $action, string $resourceType, string $resourceClass, ?int $resourceId, string $label, mixed $customerId, array $changes): void
    {
        try {
            $context = $this->contextProvider->getContext();
            $entry = $this->entryFactory->createNew();
            $entry->setCreatedAt($this->clock->now());
            $entry->setAction($action);
            $entry->setResourceType($resourceType);
            $entry->setResourceClass($resourceClass);
            $entry->setResourceId(null === $resourceId ? null : (string) $resourceId);
            $entry->setResourceLabel(mb_substr($label, 0, 255));
            if (\is_int($customerId)) {
                $entry->setOwnerType('customer');
                $entry->setOwnerId((string) $customerId);
            }
            $entry->setChanges($changes);
            $entry->setActorType($context->actorType);
            $entry->setActorId($context->actorId);
            $entry->setActorName($context->actorName);
            $entry->setSource($context->source);
            $entry->setIpAddress($context->ipAddress);
            $entry->setRequestId($context->requestId);
            $entry->setRoute($context->route);
            $this->writer->write($this->entityManager, [$entry]);
        } catch (\Throwable $exception) {
            $this->logger->error('Could not mirror the MCP event into the audit log.', ['resource' => $resourceType, 'exception' => $exception]);
        }
    }

    private function label(ConnectionInterface $connection): string
    {
        return \sprintf('%s · %s', (string) $connection->getClient()?->getName(), (string) $connection->getChannelCode());
    }
}
