<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Account;

use Sylius\Component\Core\Model\CustomerInterface;
use Tmonier\SyliusB2bMcpPlugin\Account\AccountUsability;
use Tmonier\SyliusB2bMcpPlugin\Account\AccountUsabilityCheckerInterface;

/** Test double of an account usability checker (public service, driven by the tests; usable by default). */
final class SwitchableChecker implements AccountUsabilityCheckerInterface
{
    public ?string $refusal = null;

    public ?\Throwable $failure = null;

    public int $calls = 0;

    public function check(CustomerInterface $customer): AccountUsability
    {
        ++$this->calls;
        if ($this->failure instanceof \Throwable) {
            throw $this->failure;
        }

        return null === $this->refusal ? AccountUsability::usable() : AccountUsability::refused($this->refusal);
    }
}
