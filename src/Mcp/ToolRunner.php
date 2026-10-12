<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Mcp;

use Mcp\Exception\ToolCallException;
use Tmonier\SyliusB2bMcpPlugin\Audit\ToolCallLogger;
use Tmonier\SyliusB2bMcpPlugin\Entity\ToolCallInterface;
use Tmonier\SyliusB2bMcpPlugin\Idempotency\IdempotencyStore;
use Tmonier\SyliusB2bMcpPlugin\Security\AuthenticatedConnection;
use Tmonier\SyliusB2bMcpPlugin\Security\CurrentConnection;

/**
 * Runs every B2B tool call the same way: authenticated customer and scope required, optional
 * idempotency key (cart-changing tools), audit log entry, and refusals returned to the assistant
 * as tool errors. Results are plain data (JSON): nothing in them is meant as an instruction.
 */
final class ToolRunner
{
    public function __construct(
        private readonly CurrentConnection $currentConnection,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly ToolCallLogger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments as received, for the idempotency hash and the audit log
     * @param callable(AuthenticatedConnection): array<string, mixed> $handler
     *
     * @return array<string, mixed>
     */
    public function run(string $tool, string $scope, array $arguments, callable $handler, ?string $idempotencyKey = null): array
    {
        $start = hrtime(true);
        $connection = $this->currentConnection->get();
        $claimed = false;

        try {
            if (null === $connection) {
                throw new ToolRefusal('Not signed in: this tool needs a shop account connected to the assistant (OAuth).');
            }
            if (!$connection->hasScope($scope)) {
                throw new ToolRefusal(\sprintf('This connection was not granted the "%s" permission. Reconnect the assistant to the shop and approve it.', $scope));
            }
            $customerId = $connection->customer->getId();
            if (!\is_int($customerId)) {
                throw new ToolRefusal('Not signed in: this tool needs a shop account connected to the assistant (OAuth).');
            }

            if (null !== $idempotencyKey) {
                if (!IdempotencyStore::isValidKey($idempotencyKey)) {
                    throw new ToolRefusal('idempotency_key must be 8 to 128 characters among letters, digits, ".", "_", ":" and "-". Use a new unique key (a UUID) per request.');
                }
                $stored = $this->idempotencyStore->claim($customerId, $tool, $idempotencyKey, $arguments);
                if (null !== $stored) {
                    $this->log($connection, $tool, $arguments, ToolCallInterface::OUTCOME_REPLAYED, null, $start);

                    return ['replayed' => true] + $stored;
                }
                $claimed = true;
            }

            $result = $handler($connection);

            if ($claimed) {
                $this->idempotencyStore->complete($customerId, $tool, (string) $idempotencyKey, $result);
            }
            $this->log($connection, $tool, $arguments, ToolCallInterface::OUTCOME_SUCCESS, null, $start);

            return $result;
        } catch (ToolRefusal $refusal) {
            $this->release($connection, $tool, $idempotencyKey, $claimed);
            // Calls without a customer (stdio transport) are not recorded: nothing to attribute.
            if (null !== $connection) {
                $this->log($connection, $tool, $arguments, ToolCallInterface::OUTCOME_REFUSED, $refusal->getMessage(), $start);
            }

            throw new ToolCallException($refusal->getMessage(), 0, $refusal);
        } catch (\Throwable $exception) {
            $this->release($connection, $tool, $idempotencyKey, $claimed);
            if (null !== $connection) {
                $this->log($connection, $tool, $arguments, ToolCallInterface::OUTCOME_ERROR, $exception::class, $start);
            }

            throw $exception;
        }
    }

    private function release(?AuthenticatedConnection $connection, string $tool, ?string $idempotencyKey, bool $claimed): void
    {
        $customerId = $connection?->customer->getId();
        if ($claimed && \is_int($customerId) && null !== $idempotencyKey) {
            try {
                $this->idempotencyStore->release($customerId, $tool, $idempotencyKey);
            } catch (\Throwable) {
                // A claim left behind expires after a few minutes.
            }
        }
    }

    /** @param array<string, mixed> $arguments */
    private function log(?AuthenticatedConnection $connection, string $tool, array $arguments, string $outcome, ?string $message, int|float $start): void
    {
        $durationMs = (int) round((hrtime(true) - $start) / 1_000_000);
        $this->logger->log($connection, $tool, $arguments, $outcome, $message, $durationMs, $this->currentConnection->getIpAddress());
    }
}
