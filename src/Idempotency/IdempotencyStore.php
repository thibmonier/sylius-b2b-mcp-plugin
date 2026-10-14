<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Idempotency;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Clock\ClockInterface;
use Tmonier\SyliusB2bMcpPlugin\Mcp\ToolRefusal;

/**
 * Idempotency keys of the cart-changing tools: the first call with a key claims it, a retry with
 * the same key and arguments gets the stored result instead of changing the cart twice. Written
 * with DBAL so that a unique violation (two concurrent calls) never closes the entity manager.
 */
final readonly class IdempotencyStore
{
    private const TABLE = 'tmonier_b2b_mcp_idempotency';

    /** A claim without result older than this is a crashed call: the key can be claimed again. */
    private const STALE_CLAIM = 'PT5M';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private int $ttlHours,
    ) {
    }

    public static function isValidKey(string $key): bool
    {
        return 1 === preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key);
    }

    /**
     * Claims the key, or returns the stored result of the call that used it.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>|null null when claimed (run the tool, then complete())
     *
     * @throws ToolRefusal same key with other arguments, or the first call is still running
     */
    public function claim(int $customerId, string $tool, string $key, array $arguments): ?array
    {
        $requestHash = $this->hash($arguments);
        $now = $this->clock->now();

        $row = $this->find($customerId, $tool, $key);
        if (null !== $row && $this->isExpired($row, $now)) {
            $this->connection->delete(self::TABLE, ['id' => $row['id']]);
            $row = null;
        }

        if (null === $row) {
            try {
                $this->connection->insert(self::TABLE, [
                    'customer_id' => $customerId,
                    'tool' => $tool,
                    'idempotency_key' => $key,
                    'request_hash' => $requestHash,
                    'response' => null,
                    'created_at' => $now->format('Y-m-d H:i:s'),
                ]);

                return null;
            } catch (UniqueConstraintViolationException) {
                $row = $this->find($customerId, $tool, $key);
                if (null === $row) {
                    throw new ToolRefusal('This idempotency key is being used by another call. Retry in a moment.');
                }
            }
        }

        if (!hash_equals((string) $row['request_hash'], $requestHash)) {
            throw new ToolRefusal('This idempotency key was already used with other arguments. Use a new key for a new request.');
        }
        if (null === $row['response']) {
            throw new ToolRefusal('A call with this idempotency key is still running. Retry in a moment.');
        }

        /** @var array<string, mixed>|null $response */
        $response = json_decode((string) $row['response'], true);
        if (!\is_array($response)) {
            throw new ToolRefusal('The stored result of this idempotency key is unreadable. Use a new key.');
        }

        return $response;
    }

    /** @param array<string, mixed> $response */
    public function complete(int $customerId, string $tool, string $key, array $response): void
    {
        $this->connection->update(
            self::TABLE,
            ['response' => json_encode($response, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)],
            ['customer_id' => $customerId, 'tool' => $tool, 'idempotency_key' => $key],
        );
    }

    /** The call failed: the key can be used again. */
    public function release(int $customerId, string $tool, string $key): void
    {
        $this->connection->delete(self::TABLE, ['customer_id' => $customerId, 'tool' => $tool, 'idempotency_key' => $key, 'response' => null]);
    }

    public function purge(): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE created_at < :limit',
            ['limit' => $this->clock->now()->modify(\sprintf('-%d hours', $this->ttlHours))->format('Y-m-d H:i:s')],
        );
    }

    /** @param array<string, mixed> $arguments */
    private function hash(array $arguments): string
    {
        ksort($arguments);

        return hash('sha256', json_encode($arguments, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
    }

    /** @return array{id: int|string, request_hash: string, response: string|null, created_at: string}|null */
    private function find(int $customerId, string $tool, string $key): ?array
    {
        /** @var array{id: int|string, request_hash: string, response: string|null, created_at: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, request_hash, response, created_at FROM ' . self::TABLE . ' WHERE customer_id = :customer AND tool = :tool AND idempotency_key = :key',
            ['customer' => $customerId, 'tool' => $tool, 'key' => $key],
        );

        return false === $row ? null : $row;
    }

    /** @param array{response: string|null, created_at: string} $row */
    private function isExpired(array $row, \DateTimeImmutable $now): bool
    {
        $createdAt = new \DateTimeImmutable($row['created_at']);
        if ($createdAt < $now->modify(\sprintf('-%d hours', $this->ttlHours))) {
            return true;
        }

        return null === $row['response'] && $createdAt < $now->sub(new \DateInterval(self::STALE_CLAIM));
    }
}
