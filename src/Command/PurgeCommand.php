<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Command;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tmonier\SyliusB2bMcpPlugin\Idempotency\IdempotencyStore;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;
use Tmonier\SyliusB2bMcpPlugin\Repository\ToolCallRepository;

#[AsCommand(name: 'tmonier:b2b-mcp:purge', description: 'Deletes expired OAuth tokens, clients never connected, old idempotency keys and audit log entries past the retention period.')]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly TokenStore $tokenStore,
        private readonly IdempotencyStore $idempotencyStore,
        private readonly ToolCallRepository $toolCallRepository,
        private readonly ClockInterface $clock,
        private readonly int $auditRetentionDays,
        private readonly ?\Tmonier\SyliusB2bMcpPlugin\OAuth\ApproveScopeGuard $approveScopeGuard = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tokens = $this->tokenStore->purgeExpired();
        $clients = $this->tokenStore->purgeUnusedClients();
        $approvals = $this->approveScopeGuard?->sweep() ?? 0;
        $keys = $this->idempotencyStore->purge();
        $calls = $this->toolCallRepository->deleteOlderThan($this->clock->now()->modify(\sprintf('-%d days', $this->auditRetentionDays)));

        $output->writeln(\sprintf('Deleted %d expired tokens, %d unused clients, %d idempotency keys and %d audit log entries.', $tokens, $clients, $keys, $calls));
        if ($approvals > 0) {
            $output->writeln(\sprintf('Approval decisions are disabled: b2b:approve removed from %d connections.', $approvals));
        }

        return Command::SUCCESS;
    }
}
