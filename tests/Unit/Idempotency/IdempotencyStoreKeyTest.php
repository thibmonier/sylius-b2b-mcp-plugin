<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\Idempotency;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusB2bMcpPlugin\Idempotency\IdempotencyStore;

final class IdempotencyStoreKeyTest extends TestCase
{
    #[DataProvider('validKeysProvider')]
    public function testValidKeys(string $key): void
    {
        self::assertTrue(IdempotencyStore::isValidKey($key));
    }

    /** @return iterable<string, array{string}> */
    public static function validKeysProvider(): iterable
    {
        yield '8 chars' => ['abcd1234'];
        yield '128 chars' => [str_repeat('a', 128)];
        yield 'uuid' => ['550e8400-e29b-41d4-a716-446655440000'];
        yield 'dots and underscores' => ['key.with_special:chars-ok'];
        yield 'colons' => ['tool:action:12345678'];
    }

    #[DataProvider('invalidKeysProvider')]
    public function testInvalidKeys(string $key): void
    {
        self::assertFalse(IdempotencyStore::isValidKey($key));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidKeysProvider(): iterable
    {
        yield 'too short' => ['abc1234'];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'spaces' => ['key with space'];
        yield 'special chars' => ['key@#$%^&*'];
        yield 'empty' => [''];
        yield 'slashes' => ['path/to/key1'];
    }
}
