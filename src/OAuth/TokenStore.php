<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthClient;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;

/** Persistence of the OAuth artefacts (hashed identifiers) shared by the league repositories and the authenticator. */
final class TokenStore
{
    /** @param class-string<ConnectionInterface> $connectionClass */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly string $connectionClass,
    ) {
    }

    /** @param list<string> $scopes */
    public function store(string $type, string $identifier, ConnectionInterface $connection, array $scopes, \DateTimeImmutable $expiresAt, ?string $parentIdentifier = null): OAuthToken
    {
        $token = new OAuthToken(
            $type,
            OAuthToken::hash($identifier),
            $connection,
            $scopes,
            $expiresAt,
            null === $parentIdentifier ? null : OAuthToken::hash($parentIdentifier),
        );
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $token;
    }

    public function find(string $type, string $identifier): ?OAuthToken
    {
        $token = $this->entityManager->getRepository(OAuthToken::class)->findOneBy(['identifierHash' => OAuthToken::hash($identifier)]);

        return null !== $token && $token->getType() === $type ? $token : null;
    }

    /** A usable access token: not expired, not revoked, connection still active. */
    public function findUsableAccessToken(string $identifier): ?OAuthToken
    {
        $token = $this->find(OAuthToken::TYPE_ACCESS, $identifier);

        return null !== $token && $token->isUsableAt($this->now()) ? $token : null;
    }

    public function revoke(string $type, string $identifier): void
    {
        $token = $this->find($type, $identifier);
        if (null === $token) {
            return;
        }
        $token->revoke($this->now());
        $this->entityManager->flush();
    }

    /**
     * Revokes every token of a connection (replayed code or refresh token, revocation by the
     * customer, withdrawn permission). They also expire at once: a token revoked this way is not
     * a spent one, so presenting it later is not taken for a replay (see isReplay()).
     */
    public function revokeAllForConnection(ConnectionInterface $connection): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(OAuthToken::class, 't')
            ->set('t.revokedAt', ':now')
            ->set('t.expiresAt', ':now')
            ->andWhere('t.connection = :connection')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('now', $this->now(), 'datetime_immutable')
            ->setParameter('connection', $connection)
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * A spent single-use token (rotated refresh token, redeemed code) presented again: it was
     * revoked on its own while still valid. Tokens revoked with their whole connection are not.
     */
    public function isReplay(OAuthToken $token): bool
    {
        $revokedAt = $token->getRevokedAt();

        return null !== $revokedAt && $token->getExpiresAt() > $revokedAt;
    }

    public function findConnection(?string $connectionId): ?ConnectionInterface
    {
        if (null === $connectionId || !ctype_digit($connectionId)) {
            return null;
        }
        $connection = $this->entityManager->find($this->connectionClass, (int) $connectionId);

        return $connection instanceof ConnectionInterface ? $connection : null;
    }

    public function findClient(string $identifier): ?OAuthClient
    {
        return $this->entityManager->getRepository(OAuthClient::class)->findOneBy(['identifier' => $identifier]);
    }

    /** Expired tokens and codes, kept a day for the replay detection. */
    public function purgeExpired(): int
    {
        $deleted = $this->entityManager->createQueryBuilder()
            ->delete(OAuthToken::class, 't')
            ->andWhere('t.expiresAt < :limit')
            ->setParameter('limit', $this->now()->modify('-1 day'), 'datetime_immutable')
            ->getQuery()
            ->execute()
        ;

        return \is_int($deleted) ? $deleted : 0;
    }

    /** Clients registered more than a week ago and never connected (abandoned dynamic registrations). */
    public function purgeUnusedClients(): int
    {
        $deleted = $this->entityManager->createQueryBuilder()
            ->delete(OAuthClient::class, 'c')
            ->andWhere('c.createdAt < :limit')
            ->andWhere(\sprintf('NOT EXISTS (SELECT 1 FROM %s connection WHERE connection.client = c)', $this->connectionClass))
            ->setParameter('limit', $this->now()->modify('-7 days'), 'datetime_immutable')
            ->getQuery()
            ->execute()
        ;

        return \is_int($deleted) ? $deleted : 0;
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }
}
