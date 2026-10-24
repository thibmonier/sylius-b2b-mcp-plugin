<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit;

use PHPUnit\Framework\TestCase;

/** The rate limiter of the plugin needs the Symfony Lock component: it is installed with the plugin, not by hand. */
final class ComposerRequirementsTest extends TestCase
{
    public function testSymfonyLockIsRequired(): void
    {
        self::assertArrayHasKey('symfony/lock', $this->require());
    }

    public function testSymfonyLockFollowsTheSymfonyConstraintOfTheOtherComponents(): void
    {
        $require = $this->require();

        self::assertSame($require['symfony/rate-limiter'] ?? null, $require['symfony/lock'] ?? null);
    }

    /** @return array<string, string> */
    private function require(): array
    {
        $decoded = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $require = $decoded['require'] ?? [];
        self::assertIsArray($require);

        $constraints = [];
        foreach ($require as $package => $constraint) {
            self::assertIsString($constraint);
            $constraints[(string) $package] = $constraint;
        }

        return $constraints;
    }
}
