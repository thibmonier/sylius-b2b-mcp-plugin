<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

/** Scopes this shop offers: b2b:approve only when approval decisions are enabled (opt-in). */
final readonly class ScopeCatalog
{
    public function __construct(private bool $approvalDecisions)
    {
    }

    /** @return list<string> */
    public function supported(): array
    {
        return $this->approvalDecisions ? Scopes::ALL : Scopes::DEFAULT;
    }

    public function isSupported(string $scope): bool
    {
        return \in_array($scope, $this->supported(), true);
    }
}
