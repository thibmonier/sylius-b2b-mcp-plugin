<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Account;

/** The answer of an {@see AccountUsabilityCheckerInterface}: the account is usable, or refused with a reason. */
final readonly class AccountUsability
{
    private function __construct(
        private ?string $reason,
    ) {
    }

    public static function usable(): self
    {
        return new self(null);
    }

    /**
     * @param string $reason a short machine-readable reason (e.g. "registration_pending"): kept in the logs and
     *                       the audit log, never sent to the client
     */
    public static function refused(string $reason): self
    {
        return new self($reason);
    }

    public function isUsable(): bool
    {
        return null === $this->reason;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
