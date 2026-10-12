<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use Doctrine\ORM\EntityManagerInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;

/** Registers OAuth clients (RFC 7591 metadata, or the console command). */
final class ClientRegistrar
{
    public const AUTH_METHOD_NONE = 'none';

    public const AUTH_METHODS = [self::AUTH_METHOD_NONE, 'client_secret_post', 'client_secret_basic'];

    private const MAX_REDIRECT_URIS = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RedirectUriPolicy $redirectUriPolicy,
    ) {
    }

    /**
     * @param array<mixed> $metadata client metadata of the registration request
     *
     * @return array<string, mixed> client information response
     *
     * @throws ClientRegistrationException
     */
    public function register(array $metadata): array
    {
        $redirectUris = $metadata['redirect_uris'] ?? null;
        if (!\is_array($redirectUris) || [] === $redirectUris || \count($redirectUris) > self::MAX_REDIRECT_URIS || !array_is_list($redirectUris)) {
            throw new ClientRegistrationException('invalid_redirect_uri', \sprintf('Between 1 and %d redirect URIs are required.', self::MAX_REDIRECT_URIS));
        }
        foreach ($redirectUris as $uri) {
            if (!\is_string($uri) || !$this->redirectUriPolicy->isAllowed($uri)) {
                throw new ClientRegistrationException('invalid_redirect_uri', 'This redirect URI is not allowed by the shop.');
            }
        }
        /** @var list<string> $redirectUris */
        $authMethod = $metadata['token_endpoint_auth_method'] ?? 'client_secret_basic';
        if (!\is_string($authMethod) || !\in_array($authMethod, self::AUTH_METHODS, true)) {
            throw new ClientRegistrationException('invalid_client_metadata', 'Supported token endpoint authentication methods: ' . implode(', ', self::AUTH_METHODS) . '.');
        }
        $grantTypes = $metadata['grant_types'] ?? ['authorization_code'];
        if (!\is_array($grantTypes) || [] !== array_diff($grantTypes, ['authorization_code', 'refresh_token'])) {
            throw new ClientRegistrationException('invalid_client_metadata', 'Supported grant types: authorization_code, refresh_token.');
        }
        $responseTypes = $metadata['response_types'] ?? ['code'];
        if (!\is_array($responseTypes) || [] !== array_diff($responseTypes, ['code'])) {
            throw new ClientRegistrationException('invalid_client_metadata', 'Supported response types: code.');
        }
        $name = $metadata['client_name'] ?? null;

        [$client, $secret] = $this->create(\is_string($name) ? $name : '', $redirectUris, self::AUTH_METHOD_NONE !== $authMethod);

        return array_filter([
            'client_id' => $client->getIdentifier(),
            'client_id_issued_at' => $client->getCreatedAt()->getTimestamp(),
            'client_secret' => $secret,
            'client_secret_expires_at' => null === $secret ? null : 0,
            'client_name' => $client->getName(),
            'redirect_uris' => $client->getRedirectUris(),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $authMethod,
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @param list<string> $redirectUris already checked against the policy
     *
     * @return array{OAuthClient, string|null} the client and its secret (shown once)
     */
    public function create(string $name, array $redirectUris, bool $confidential): array
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name));
        if ('' === $name) {
            $name = 'MCP client';
        }
        $secret = $confidential ? bin2hex(random_bytes(32)) : null;
        $client = new OAuthClient(
            bin2hex(random_bytes(16)),
            mb_substr($name, 0, 100),
            array_values(array_unique($redirectUris)),
            null === $secret ? null : password_hash($secret, \PASSWORD_DEFAULT),
        );
        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return [$client, $secret];
    }
}
