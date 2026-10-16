<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Account;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\CustomerInterface;

/** Asks the checkers in order: the first refusal wins, a checker that throws refuses (fail closed). */
final readonly class AccountUsabilityChain
{
    public const FAILURE_REASON = 'checker_failed';

    /** @param iterable<AccountUsabilityCheckerInterface> $checkers */
    public function __construct(
        private iterable $checkers,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function check(CustomerInterface $customer): AccountUsability
    {
        foreach ($this->checkers as $checker) {
            try {
                $result = $checker->check($customer);
            } catch (\Throwable $exception) {
                $this->logger?->error('An account usability checker failed: the account is refused.', ['checker' => $checker::class, 'customer' => $customer->getId(), 'exception' => $exception]);

                return AccountUsability::refused(self::FAILURE_REASON);
            }

            if (!$result->isUsable()) {
                $this->logger?->info('The account of a customer is not usable for the MCP server.', ['checker' => $checker::class, 'customer' => $customer->getId(), 'reason' => $result->reason()]);

                return $result;
            }
        }

        return AccountUsability::usable();
    }
}
