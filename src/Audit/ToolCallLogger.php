<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Audit;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCall;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;

/** Audit log of the B2B tool calls (who, which assistant, which tool, arguments, outcome). */
final class ToolCallLogger
{
    private const MAX_ARGUMENTS_LENGTH = 2000;

    /** Free text written by the customer: only its length is recorded. */
    private const FREE_TEXT_ARGUMENTS = ['message'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly AuditMirrorInterface $mirror = new NullAuditMirror(),
    ) {
    }

    /** @param array<string, mixed> $arguments */
    public function log(?AuthenticatedConnection $connection, string $tool, array $arguments, string $outcome, ?string $message, int $durationMs, ?string $ipAddress): void
    {
        foreach (self::FREE_TEXT_ARGUMENTS as $name) {
            if (\is_string($arguments[$name] ?? null)) {
                $arguments[$name] = \sprintf('[%d characters]', mb_strlen($arguments[$name]));
            }
        }
        $encoded = json_encode($arguments, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE);
        $encoded = false === $encoded ? '{}' : $encoded;
        if (mb_strlen($encoded) > self::MAX_ARGUMENTS_LENGTH) {
            $encoded = mb_substr($encoded, 0, self::MAX_ARGUMENTS_LENGTH) . '…';
        }

        try {
            if (!$this->entityManager->isOpen()) {
                throw new \RuntimeException('The entity manager is closed.');
            }
            $call = new ToolCall(
                $connection?->connection,
                $connection?->customer->getEmail(),
                $connection?->connection->getClient()?->getName(),
                $connection?->channel->getCode(),
                $tool,
                $encoded,
                $outcome,
                null === $message ? null : mb_substr($message, 0, 255),
                $durationMs,
                $ipAddress,
            );
            $this->entityManager->persist($call);
            $this->entityManager->flush();
            $this->mirror->toolCalled($call);
        } catch (\Throwable $exception) {
            // The audit log never breaks a tool call; the application log keeps the trace.
            $this->logger->error('Could not record the MCP tool call.', ['tool' => $tool, 'outcome' => $outcome, 'exception' => $exception]);
        }
    }
}
