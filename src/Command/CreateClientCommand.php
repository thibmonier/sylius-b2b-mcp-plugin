<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ClientRegistrar;
use Tmonier\SyliusB2bMcpPlugin\OAuth\RedirectUriPolicy;

/** For assistants configured with a fixed client ID (and secret) instead of dynamic registration. */
#[AsCommand(name: 'tmonier:b2b-mcp:create-client', description: 'Creates an OAuth client for an AI assistant (client ID, and secret with --confidential).')]
final class CreateClientCommand extends Command
{
    public function __construct(
        private readonly ClientRegistrar $registrar,
        private readonly RedirectUriPolicy $redirectUriPolicy,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Name shown to the customer on the consent page, e.g. "Claude"')
            ->addOption('redirect-uri', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Redirect URI (repeat the option for several), allowed by tmonier_sylius_b2b_mcp.oauth.redirect_uris')
            ->addOption('confidential', null, InputOption::VALUE_NONE, 'Generate a client secret (shown once)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $redirectUris */
        $redirectUris = $input->getOption('redirect-uri');
        if ([] === $redirectUris) {
            $output->writeln('<error>At least one --redirect-uri is required.</error>');

            return Command::INVALID;
        }
        foreach ($redirectUris as $uri) {
            if (!$this->redirectUriPolicy->isAllowed($uri)) {
                $output->writeln(\sprintf('<error>The redirect URI "%s" is not allowed: add it to tmonier_sylius_b2b_mcp.oauth.redirect_uris first.</error>', $uri));

                return Command::INVALID;
            }
        }

        /** @var string $name */
        $name = $input->getArgument('name');
        [$client, $secret] = $this->registrar->create($name, $redirectUris, (bool) $input->getOption('confidential'));

        $output->writeln(\sprintf('Client ID: %s', $client->getIdentifier()));
        if (null !== $secret) {
            $output->writeln(\sprintf('Client secret (shown once): %s', $secret));
        }

        return Command::SUCCESS;
    }
}
